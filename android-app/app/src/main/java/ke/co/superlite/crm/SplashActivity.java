package ke.co.superlite.crm;

import android.content.Intent;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.animation.AlphaAnimation;
import android.view.animation.Animation;
import android.view.animation.AnimationSet;
import android.view.animation.DecelerateInterpolator;
import android.view.animation.ScaleAnimation;
import android.widget.ImageView;
import android.widget.TextView;
import androidx.appcompat.app.AppCompatActivity;

public class SplashActivity extends AppCompatActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_splash);

        ImageView logo = findViewById(R.id.splashLogo);
        TextView title = findViewById(R.id.splashTitle);
        TextView subtitle = findViewById(R.id.splashSubtitle);
        View progressIndicator = findViewById(R.id.splashProgress);

        AnimationSet logoAnim = new AnimationSet(true);
        logoAnim.setInterpolator(new DecelerateInterpolator());

        ScaleAnimation scale = new ScaleAnimation(0.5f, 1.0f, 0.5f, 1.0f,
                Animation.RELATIVE_TO_SELF, 0.5f, Animation.RELATIVE_TO_SELF, 0.5f);
        scale.setDuration(600);

        AlphaAnimation fadeIn = new AlphaAnimation(0.0f, 1.0f);
        fadeIn.setDuration(600);

        logoAnim.addAnimation(scale);
        logoAnim.addAnimation(fadeIn);
        logo.startAnimation(logoAnim);

        AlphaAnimation titleFade = new AlphaAnimation(0.0f, 1.0f);
        titleFade.setDuration(500);
        titleFade.setStartOffset(400);
        title.startAnimation(titleFade);

        AlphaAnimation subtitleFade = new AlphaAnimation(0.0f, 1.0f);
        subtitleFade.setDuration(500);
        subtitleFade.setStartOffset(600);
        subtitle.startAnimation(subtitleFade);

        AlphaAnimation progressFade = new AlphaAnimation(0.0f, 1.0f);
        progressFade.setDuration(300);
        progressFade.setStartOffset(800);
        progressIndicator.startAnimation(progressFade);

        new Handler(Looper.getMainLooper()).postDelayed(() -> {
            startActivity(new Intent(SplashActivity.this, MainActivity.class));
            overridePendingTransition(android.R.anim.fade_in, android.R.anim.fade_out);
            finish();
        }, 2000);
    }
}
