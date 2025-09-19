<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class InstallApplication extends Command
{
    private const REQUIRED_EXTENSIONS = [
        'pdo_mysql', 'openssl', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'gd', 'curl', 'bcmath',
    ];

    private const DEFAULT_CRON = '* * * * * php /path/to/project/artisan schedule:run >/dev/null 2>&1';

    protected $signature = 'app:install {--force : Run without confirmation prompts.} {--with-demo : Seed demo fixtures if available.}';

    protected $description = 'Configure environment variables, run migrations, and verify platform prerequisites for Safe Handover.';

    public function handle(): int
    {
        $this->components->info('Safe Handover Installer');

        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->warn('You are running the installer in production. Re-run with --force if this is intentional.');

            return self::FAILURE;
        }

        $failures = $this->checkSystemRequirements();

        if (! empty($failures)) {
            table(['Requirement', 'Status'], $failures);
            $this->error('System requirements check failed. Please resolve the items above before continuing.');

            return self::FAILURE;
        }

        $environment = $this->collectEnvironmentConfiguration();

        if (! $this->option('force') && File::exists(base_path('.env'))) {
            $overwrite = confirm('A .env file already exists. Overwrite with the new configuration?', default: false);

            if (! $overwrite) {
                $this->comment('Installer aborted at user request.');

                return self::SUCCESS;
            }
        }

        $this->writeEnvironmentFile($environment);

        $this->info('Environment file written. Generating application key...');
        Artisan::call('key:generate', ['--force' => true]);

        $this->info('Clearing cached configuration...');
        Artisan::call('config:clear');

        $this->info('Running database migrations...');
        Artisan::call('migrate', ['--force' => true]);

        $seedOptions = ['--force' => true];

        if ($this->option('with-demo')) {
            $seedOptions['--class'] = 'Database\\Seeders\\DemoSeeder';
        }

        $this->info('Seeding base data...');
        Artisan::call('db:seed', $seedOptions);

        $this->info('Linking storage directory (public/storage)...');
        Artisan::call('storage:link');

        $this->line('');
        note('Installer complete. Next steps');
        $this->line('• Configure a cron job: '.self::DEFAULT_CRON);
        $this->line('• Configure queue worker: php artisan queue:work --tries=1');
        $this->line('• Stripe webhooks URL: '.rtrim($environment['app_url'], '/').'/stripe/webhook');
        $this->line('• Realtime auth endpoint: '.rtrim($environment['app_url'], '/').'/broadcasting/auth');
        $this->line('• Health check: '.rtrim($environment['app_url'], '/').'/api/health');

        return self::SUCCESS;
    }

    private function checkSystemRequirements(): array
    {
        $rows = [];

        if (! version_compare(PHP_VERSION, '8.4.0', '>=')) {
            $rows[] = ['PHP >= 8.4.0', 'FAIL (Current: '.PHP_VERSION.')'];
        }

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            if (! extension_loaded($extension)) {
                $rows[] = ["PHP extension: {$extension}", 'MISSING'];
            }
        }

        return $rows;
    }

    private function collectEnvironmentConfiguration(): array
    {
        $current = static fn (string $key, ?string $fallback = null) => env($key, $fallback ?? '');

        $appName = text(
            label: 'Application name',
            default: $current('APP_NAME', 'Safe Handover'),
            required: true
        );

        $appUrl = text(
            label: 'Application URL',
            default: $current('APP_URL', 'https://safe-handover.test'),
            required: true
        );

        $appEnv = select(
            label: 'Application environment',
            options: [
                'production' => 'production',
                'staging' => 'staging',
                'local' => 'local',
            ],
            default: $current('APP_ENV', 'local')
        );

        $timezone = text(
            label: 'Default timezone',
            default: $current('APP_TIMEZONE', 'Europe/London'),
            required: true
        );

        $dbHost = text('Database host', default: $current('DB_HOST', '127.0.0.1'), required: true);
        $dbPort = text('Database port', default: $current('DB_PORT', '3306'), required: true);
        $dbName = text('Database name', default: $current('DB_DATABASE', 'handover'), required: true);
        $dbUser = text('Database username', default: $current('DB_USERNAME', 'handover'), required: true);
        $dbPassword = text('Database password', default: $current('DB_PASSWORD'));

        $mailMailer = select(
            label: 'Mail transport',
            options: ['smtp' => 'SMTP', 'ses' => 'Amazon SES', 'log' => 'Log only'],
            default: $current('MAIL_MAILER', 'smtp')
        );
        $mailHost = text('Mail host', default: $current('MAIL_HOST', 'smtp.mailgun.org'));
        $mailPort = text('Mail port', default: $current('MAIL_PORT', '587'), required: true);
        $mailUser = text('Mail username', default: $current('MAIL_USERNAME'));
        $mailPassword = text('Mail password', default: $current('MAIL_PASSWORD'));
        $mailEncryption = text('Mail encryption (tls/ssl/empty)', default: $current('MAIL_ENCRYPTION', 'tls'));
        $mailFromAddress = text('Mail from address', default: $current('MAIL_FROM_ADDRESS', 'alerts@safe-handover.example'));
        $mailFromName = text('Mail from name', default: $current('MAIL_FROM_NAME', $appName));

        $broadcastDriver = select(
            label: 'Realtime driver',
            options: ['pusher' => 'Pusher/Ably'],
            default: 'pusher'
        );
        $pusherKey = text('Pusher/Ably key', default: $current('PUSHER_APP_KEY'));
        $pusherSecret = text('Pusher/Ably secret', default: $current('PUSHER_APP_SECRET'));
        $pusherId = text('Pusher app ID (or Ably app ID)', default: $current('PUSHER_APP_ID'));
        $pusherCluster = text('Pusher cluster (use eu for Ably)', default: $current('PUSHER_APP_CLUSTER', 'eu'));
        $pusherHost = text('Pusher host override (leave blank for default)', default: $current('PUSHER_HOST'));
        $pusherPort = text('Pusher port', default: $current('PUSHER_PORT', '443'));
        $pusherScheme = text('Pusher scheme', default: $current('PUSHER_SCHEME', 'https'));

        $stripeKey = text('Stripe publishable key', default: $current('STRIPE_KEY'));
        $stripeSecret = text('Stripe secret key', default: $current('STRIPE_SECRET'));
        $stripeWebhookSecret = text('Stripe webhook signing secret', default: $current('STRIPE_WEBHOOK_SECRET'));
        $stripeClientId = text('Stripe Connect client ID', default: $current('STRIPE_CLIENT_ID'));
        $stripeConnectWebhookSecret = text('Stripe Connect webhook secret', default: $current('STRIPE_CONNECT_WEBHOOK_SECRET'));
        $stripeIdentityKey = text('Stripe Identity secret key', default: $current('STRIPE_IDENTITY_KEY'));
        $stripeIdentityRefresh = text('Stripe Identity refresh URL', default: $current('STRIPE_IDENTITY_REFRESH_URL', rtrim($appUrl, '/').'/identity/refresh'));
        $stripeIdentityReturn = text('Stripe Identity return URL', default: $current('STRIPE_IDENTITY_RETURN_URL', rtrim($appUrl, '/').'/identity/return'));

        $smsDriver = select(
            label: 'SMS provider',
            options: ['twilio' => 'Twilio', 'log' => 'Log only'],
            default: $current('SMS_DRIVER', 'twilio')
        );
        $twilioSid = text('Twilio Account SID', default: $current('TWILIO_ACCOUNT_SID'));
        $twilioToken = text('Twilio Auth Token', default: $current('TWILIO_AUTH_TOKEN'));
        $twilioServiceSid = text('Twilio Messaging Service SID (optional)', default: $current('TWILIO_MESSAGING_SERVICE_SID'));
        $twilioFrom = text('Twilio sender number (if no Messaging Service SID)', default: $current('TWILIO_FROM'));

        $s3Key = text('AWS Access Key ID', default: $current('AWS_ACCESS_KEY_ID'));
        $s3Secret = text('AWS Secret Access Key', default: $current('AWS_SECRET_ACCESS_KEY'));
        $s3Region = text('AWS default region', default: $current('AWS_DEFAULT_REGION', 'eu-west-2'));
        $s3Bucket = text('S3 bucket name', default: $current('AWS_BUCKET', 'safe-handover-uploads'));
        $s3Endpoint = text('S3 endpoint (leave blank for AWS)', default: $current('AWS_ENDPOINT'));

        return [
            'app_name' => $appName,
            'app_env' => $appEnv,
            'app_url' => $appUrl,
            'app_timezone' => $timezone,
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass' => $dbPassword,
            'mail_mailer' => $mailMailer,
            'mail_host' => $mailHost,
            'mail_port' => $mailPort,
            'mail_user' => $mailUser,
            'mail_password' => $mailPassword,
            'mail_encryption' => $mailEncryption,
            'mail_from_address' => $mailFromAddress,
            'mail_from_name' => $mailFromName,
            'broadcast_driver' => $broadcastDriver,
            'pusher_key' => $pusherKey,
            'pusher_secret' => $pusherSecret,
            'pusher_app_id' => $pusherId,
            'pusher_cluster' => $pusherCluster,
            'pusher_host' => $pusherHost,
            'pusher_port' => $pusherPort,
            'pusher_scheme' => $pusherScheme,
            'stripe_key' => $stripeKey,
            'stripe_secret' => $stripeSecret,
            'stripe_webhook_secret' => $stripeWebhookSecret,
            'stripe_client_id' => $stripeClientId,
            'stripe_connect_webhook_secret' => $stripeConnectWebhookSecret,
            'stripe_identity_key' => $stripeIdentityKey,
            'stripe_identity_refresh' => $stripeIdentityRefresh,
            'stripe_identity_return' => $stripeIdentityReturn,
            'sms_driver' => $smsDriver,
            'twilio_sid' => $twilioSid,
            'twilio_token' => $twilioToken,
            'twilio_service_sid' => $twilioServiceSid,
            'twilio_from' => $twilioFrom,
            's3_key' => $s3Key,
            's3_secret' => $s3Secret,
            's3_region' => $s3Region,
            's3_bucket' => $s3Bucket,
            's3_endpoint' => $s3Endpoint,
        ];
    }

    private function writeEnvironmentFile(array $environment): void
    {
        $lines = [
            'APP_NAME="'.addslashes($environment['app_name']).'"',
            'APP_ENV='.$environment['app_env'],
            'APP_KEY=',
            'APP_DEBUG=false',
            'APP_URL='.$environment['app_url'],
            'APP_TIMEZONE='.$environment['app_timezone'],
            '',
            'LOG_CHANNEL=stack',
            'LOG_LEVEL=info',
            '',
            'DB_CONNECTION=mysql',
            'DB_HOST='.$environment['db_host'],
            'DB_PORT='.$environment['db_port'],
            'DB_DATABASE='.$environment['db_name'],
            'DB_USERNAME='.$environment['db_user'],
            'DB_PASSWORD='.$environment['db_pass'],
            '',
            'BROADCAST_DRIVER='.$environment['broadcast_driver'],
            'CACHE_DRIVER=file',
            'FILESYSTEM_DISK=s3',
            'QUEUE_CONNECTION=database',
            'SESSION_DRIVER=file',
            'SESSION_LIFETIME=120',
            '',
            'MEMCACHED_HOST=127.0.0.1',
            '',
            'REDIS_HOST=127.0.0.1',
            'REDIS_PASSWORD=null',
            'REDIS_PORT=6379',
            '',
            'MAIL_MAILER='.$environment['mail_mailer'],
            'MAIL_HOST='.$environment['mail_host'],
            'MAIL_PORT='.$environment['mail_port'],
            'MAIL_USERNAME='.$environment['mail_user'],
            'MAIL_PASSWORD='.$environment['mail_password'],
            'MAIL_ENCRYPTION='.$environment['mail_encryption'],
            'MAIL_FROM_ADDRESS='.$environment['mail_from_address'],
            'MAIL_FROM_NAME="'.addslashes($environment['mail_from_name']).'"',
            '',
            'AWS_ACCESS_KEY_ID='.$environment['s3_key'],
            'AWS_SECRET_ACCESS_KEY='.$environment['s3_secret'],
            'AWS_DEFAULT_REGION='.$environment['s3_region'],
            'AWS_BUCKET='.$environment['s3_bucket'],
            'AWS_USE_PATH_STYLE_ENDPOINT=false',
            'AWS_ENDPOINT='.$environment['s3_endpoint'],
            '',
            'PUSHER_APP_ID='.$environment['pusher_app_id'],
            'PUSHER_APP_KEY='.$environment['pusher_key'],
            'PUSHER_APP_SECRET='.$environment['pusher_secret'],
            'PUSHER_APP_CLUSTER='.$environment['pusher_cluster'],
            'PUSHER_HOST='.$environment['pusher_host'],
            'PUSHER_PORT='.$environment['pusher_port'],
            'PUSHER_SCHEME='.$environment['pusher_scheme'],
            '',
            'STRIPE_KEY='.$environment['stripe_key'],
            'STRIPE_SECRET='.$environment['stripe_secret'],
            'STRIPE_WEBHOOK_SECRET='.$environment['stripe_webhook_secret'],
            'STRIPE_CLIENT_ID='.$environment['stripe_client_id'],
            'STRIPE_CONNECT_WEBHOOK_SECRET='.$environment['stripe_connect_webhook_secret'],
            'STRIPE_IDENTITY_KEY='.$environment['stripe_identity_key'],
            'STRIPE_IDENTITY_REFRESH_URL='.$environment['stripe_identity_refresh'],
            'STRIPE_IDENTITY_RETURN_URL='.$environment['stripe_identity_return'],
            '',
            'SMS_DRIVER='.$environment['sms_driver'],
            'TWILIO_ACCOUNT_SID='.$environment['twilio_sid'],
            'TWILIO_AUTH_TOKEN='.$environment['twilio_token'],
            'TWILIO_MESSAGING_SERVICE_SID='.$environment['twilio_service_sid'],
            'TWILIO_FROM='.$environment['twilio_from'],
        ];

        File::put(base_path('.env'), implode(PHP_EOL, $lines).PHP_EOL);
    }
}