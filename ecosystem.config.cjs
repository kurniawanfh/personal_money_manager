module.exports = {
  apps: [
    {
      name: 'money-manager-api',
      script: 'artisan',
      interpreter: 'php',
      args: 'serve --host=127.0.0.1 --port=8000',
      cwd: '/root/personal_money_manager/backend',
      autorestart: true,
      watch: false,
      max_memory_restart: '256M',
      env: {
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        APP_URL: 'https://api-money.kurt.web.id'
      }
    }
  ]
};
