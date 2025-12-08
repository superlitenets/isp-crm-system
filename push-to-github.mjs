import { Octokit } from '@octokit/rest';
import { readFileSync, readdirSync, statSync, existsSync } from 'fs';
import { join, relative } from 'path';

let connectionSettings;

async function getAccessToken() {
  if (connectionSettings && connectionSettings.settings.expires_at && new Date(connectionSettings.settings.expires_at).getTime() > Date.now()) {
    return connectionSettings.settings.access_token;
  }
  
  const hostname = process.env.REPLIT_CONNECTORS_HOSTNAME;
  const xReplitToken = process.env.REPL_IDENTITY 
    ? 'repl ' + process.env.REPL_IDENTITY 
    : process.env.WEB_REPL_RENEWAL 
    ? 'depl ' + process.env.WEB_REPL_RENEWAL 
    : null;

  if (!xReplitToken) {
    throw new Error('X_REPLIT_TOKEN not found');
  }

  connectionSettings = await fetch(
    'https://' + hostname + '/api/v2/connection?include_secrets=true&connector_names=github',
    {
      headers: {
        'Accept': 'application/json',
        'X_REPLIT_TOKEN': xReplitToken
      }
    }
  ).then(res => res.json()).then(data => data.items?.[0]);

  const accessToken = connectionSettings?.settings?.access_token || connectionSettings.settings?.oauth?.credentials?.access_token;

  if (!connectionSettings || !accessToken) {
    throw new Error('GitHub not connected');
  }
  return accessToken;
}

async function getGitHubClient() {
  const accessToken = await getAccessToken();
  return new Octokit({ auth: accessToken });
}

function getAllFiles(dir, baseDir = dir) {
  const files = [];
  const excludeDirs = ['node_modules', '.git', '.cache', '.upm', '.config', 'vendor', 'logs', 'whatsapp-service/node_modules', 'whatsapp-service/.wwebjs_auth', 'public/downloads', 'public/download'];
  const excludeFiles = ['.replit', 'replit.nix', '.breakpoints', '.env', 'isp-crm-complete.zip', 'isp-crm-docker.zip', 'push-to-github.mjs', 'package-lock.json', 'whatsapp-service/package-lock.json', 'whatsapp-service/.api_secret'];
  
  for (const item of readdirSync(dir)) {
    const fullPath = join(dir, item);
    const relativePath = relative(baseDir, fullPath);
    
    if (excludeDirs.some(d => relativePath === d || relativePath.startsWith(d + '/'))) continue;
    if (excludeFiles.includes(relativePath) || excludeFiles.includes(item)) continue;
    if (item.startsWith('.') && !['env.example', '.htaccess', '.gitignore'].includes(item) && item !== '.env.example') continue;
    if (relativePath.endsWith('.zip')) continue;
    
    try {
      const stat = statSync(fullPath);
      if (stat.isDirectory()) {
        files.push(...getAllFiles(fullPath, baseDir));
      } else if (stat.isFile() && stat.size < 500000) {
        files.push(relativePath);
      }
    } catch (e) {}
  }
  return files;
}

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function main() {
  console.log('Connecting to GitHub...');
  const octokit = await getGitHubClient();
  
  const { data: user } = await octokit.users.getAuthenticated();
  console.log(`Authenticated as: ${user.login}`);
  
  const repoName = 'isp-crm-system';
  
  let repo;
  try {
    const { data } = await octokit.repos.get({ owner: user.login, repo: repoName });
    repo = data;
    console.log(`Repository exists: ${repo.html_url}`);
  } catch (e) {
    if (e.status === 404) {
      console.log('Creating new repository...');
      const { data } = await octokit.repos.createForAuthenticatedUser({
        name: repoName,
        description: 'ISP CRM & Ticketing System with WhatsApp Integration',
        private: false,
        auto_init: true
      });
      repo = data;
      console.log(`Created repository: ${repo.html_url}`);
      await sleep(3000);
    } else {
      throw e;
    }
  }
  
  console.log('Getting files to upload...');
  const files = getAllFiles('/home/runner/workspace');
  console.log(`Found ${files.length} files to upload`);
  
  let mainSha, branch = 'main';
  try {
    const { data: ref } = await octokit.git.getRef({ owner: user.login, repo: repoName, ref: 'heads/main' });
    mainSha = ref.object.sha;
  } catch (e) {
    try {
      const { data: ref } = await octokit.git.getRef({ owner: user.login, repo: repoName, ref: 'heads/master' });
      mainSha = ref.object.sha;
      branch = 'master';
    } catch (e2) {
      console.log('No branch found, creating initial commit...');
      const { data: blob } = await octokit.git.createBlob({
        owner: user.login, repo: repoName,
        content: Buffer.from('# ISP CRM System\n').toString('base64'),
        encoding: 'base64'
      });
      const { data: tree } = await octokit.git.createTree({
        owner: user.login, repo: repoName,
        tree: [{ path: 'README.md', mode: '100644', type: 'blob', sha: blob.sha }]
      });
      const { data: commit } = await octokit.git.createCommit({
        owner: user.login, repo: repoName,
        message: 'Initial commit', tree: tree.sha, parents: []
      });
      await octokit.git.createRef({
        owner: user.login, repo: repoName,
        ref: 'refs/heads/main', sha: commit.sha
      });
      mainSha = commit.sha;
    }
  }
  
  const { data: baseCommit } = await octokit.git.getCommit({ owner: user.login, repo: repoName, commit_sha: mainSha });
  
  console.log('Creating file blobs (with rate limit handling)...');
  const treeItems = [];
  let count = 0;
  
  for (const file of files) {
    try {
      const content = readFileSync(join('/home/runner/workspace', file));
      
      let retries = 3;
      while (retries > 0) {
        try {
          const { data: blob } = await octokit.git.createBlob({
            owner: user.login,
            repo: repoName,
            content: content.toString('base64'),
            encoding: 'base64'
          });
          treeItems.push({ path: file, mode: '100644', type: 'blob', sha: blob.sha });
          count++;
          if (count % 10 === 0) process.stdout.write(`\r${count}/${files.length} files uploaded`);
          await sleep(100);
          break;
        } catch (apiErr) {
          if (apiErr.status === 403 && apiErr.message.includes('rate limit')) {
            console.log(`\nRate limited, waiting 60s...`);
            await sleep(60000);
            retries--;
          } else {
            throw apiErr;
          }
        }
      }
    } catch (e) {
      console.log(`\nSkipping ${file}: ${e.message.substring(0, 50)}`);
    }
  }
  
  console.log(`\n${treeItems.length} files ready for commit`);
  
  if (treeItems.length === 0) {
    console.log('No files to commit!');
    return;
  }
  
  console.log('Creating tree...');
  const { data: tree } = await octokit.git.createTree({
    owner: user.login,
    repo: repoName,
    base_tree: baseCommit.tree.sha,
    tree: treeItems
  });
  
  console.log('Creating commit...');
  const { data: commit } = await octokit.git.createCommit({
    owner: user.login,
    repo: repoName,
    message: 'ISP CRM System - Full source code with Docker support',
    tree: tree.sha,
    parents: [mainSha]
  });
  
  console.log('Updating branch...');
  await octokit.git.updateRef({ owner: user.login, repo: repoName, ref: `heads/${branch}`, sha: commit.sha });
  
  console.log('\n✅ Done! Repository URL:');
  console.log(repo.html_url);
  console.log('\nClone with:');
  console.log(`git clone ${repo.clone_url}`);
}

main().catch(console.error);
