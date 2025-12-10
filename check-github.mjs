import { Octokit } from '@octokit/rest';

let connectionSettings;

async function getAccessToken() {
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

  const accessToken = connectionSettings?.settings?.access_token || connectionSettings?.settings?.oauth?.credentials?.access_token;
  if (!accessToken) throw new Error('GitHub not connected');
  return accessToken;
}

async function main() {
  const token = await getAccessToken();
  const octokit = new Octokit({ auth: token });
  
  // Get repo info
  const { data: repo } = await octokit.repos.get({
    owner: 'superlitenets',
    repo: 'isp-crm-system'
  });
  
  console.log('=== Repository Info ===');
  console.log('Repository:', repo.full_name);
  console.log('Default branch:', repo.default_branch);
  console.log('Last push:', repo.pushed_at);
  console.log('Visibility:', repo.private ? 'Private' : 'Public');
  
  // Get recent commits on remote
  const { data: commits } = await octokit.repos.listCommits({
    owner: 'superlitenets',
    repo: 'isp-crm-system',
    per_page: 15
  });
  
  console.log('\n=== Recent Commits on GitHub ===');
  commits.forEach(c => {
    console.log(`${c.sha.substring(0,7)} - ${c.commit.message.split('\n')[0]}`);
  });
}

main().catch(e => console.error('Error:', e.message));
