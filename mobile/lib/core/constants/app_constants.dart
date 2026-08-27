class AppConstants {
  static const String appName = 'Personal Money Manager';
  static const String apiBaseUrl = 'http://10.0.2.2:8000/api/v1'; // Local Android emulator or localhost
  static const String storageTokenKey = 'auth_bearer_token';
  static const String storageUserKey = 'auth_user_data';

  // Leak Threshold Defaults
  static const double defaultDripMaxSingle = 25000.0;
  static const double defaultDripMonthlyThreshold = 500000.0;
  static const double defaultSurgePercentage = 150.0;
  static const int defaultZombieInactivityDays = 60;

  // Voice Quota
  static const int freeMonthlyVoiceLimit = 10;
}
