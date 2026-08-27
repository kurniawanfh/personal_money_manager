import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:timezone/timezone.dart' as tz;
import 'package:timezone/data/latest.dart' as tz_data;

class LocalNotificationService {
  final FlutterLocalNotificationsPlugin _notificationsPlugin = FlutterLocalNotificationsPlugin();
  bool _isInitialized = false;

  LocalNotificationService();

  void initTimezoneOnly({String defaultTimezone = 'Asia/Jakarta'}) {
    tz_data.initializeTimeZones();
    try {
      tz.setLocalLocation(tz.getLocation(defaultTimezone));
    } catch (_) {
      tz.setLocalLocation(tz.getLocation('UTC'));
    }
  }

  Future<void> initialize({String defaultTimezone = 'Asia/Jakarta'}) async {
    if (_isInitialized) return;

    initTimezoneOnly(defaultTimezone: defaultTimezone);

    const AndroidInitializationSettings androidSettings =
        AndroidInitializationSettings('@mipmap/ic_launcher');
    const DarwinInitializationSettings iosSettings = DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );

    const InitializationSettings initSettings = InitializationSettings(
      android: androidSettings,
      iOS: iosSettings,
    );

    await _notificationsPlugin.initialize(initSettings);
    _isInitialized = true;
  }

  /// Generate a deterministic 32-bit integer ID for notification scheduling.
  int generateNotificationId(String subscriptionId, String reminderType) {
    final key = '$subscriptionId:$reminderType';
    return key.hashCode.abs() % 2147483647;
  }

  /// Calculate the scheduled TZDateTime for a reminder in device local timezone.
  tz.TZDateTime calculateReminderDate({
    required DateTime billingDate,
    required int daysBefore,
    int targetHour = 9,
    int targetMinute = 0,
  }) {
    final reminderDate = billingDate.subtract(Duration(days: daysBefore));
    final scheduledDate = tz.TZDateTime.local(
      reminderDate.year,
      reminderDate.month,
      reminderDate.day,
      targetHour,
      targetMinute,
    );
    return scheduledDate;
  }

  /// Schedule H-3, H-1, and Due Date alarms for an active subscription.
  Future<void> scheduleSubscriptionReminders({
    required String subscriptionId,
    required String subscriptionName,
    required double amount,
    required DateTime nextBillingDate,
    bool remindH3 = true,
    bool remindH1 = true,
  }) async {
    const NotificationDetails notificationDetails = NotificationDetails(
      android: AndroidNotificationDetails(
        'subscription_reminders',
        'Subscription Reminders',
        channelDescription: 'Notifications for upcoming subscription renewals',
        importance: Importance.high,
        priority: Priority.high,
      ),
      iOS: DarwinNotificationDetails(sound: 'default'),
    );

    final now = tz.TZDateTime.now(tz.local);

    // Schedule H-3 Reminder
    if (remindH3) {
      final h3Date = calculateReminderDate(billingDate: nextBillingDate, daysBefore: 3);
      if (h3Date.isAfter(now)) {
        await _notificationsPlugin.zonedSchedule(
          generateNotificationId(subscriptionId, 'h3'),
          'Upcoming Renewal: $subscriptionName',
          'Your $subscriptionName renewal is due in 3 days.',
          h3Date,
          notificationDetails,
          androidScheduleMode: AndroidScheduleMode.exactAllowWhileIdle,
          uiLocalNotificationDateInterpretation:
              UILocalNotificationDateInterpretation.absoluteTime,
        );
      }
    }

    // Schedule H-1 Reminder
    if (remindH1) {
      final h1Date = calculateReminderDate(billingDate: nextBillingDate, daysBefore: 1);
      if (h1Date.isAfter(now)) {
        await _notificationsPlugin.zonedSchedule(
          generateNotificationId(subscriptionId, 'h1'),
          'Upcoming Renewal Tomorrow: $subscriptionName',
          'Your $subscriptionName renewal is due tomorrow.',
          h1Date,
          notificationDetails,
          androidScheduleMode: AndroidScheduleMode.exactAllowWhileIdle,
          uiLocalNotificationDateInterpretation:
              UILocalNotificationDateInterpretation.absoluteTime,
        );
      }
    }
  }

  /// Cancel all scheduled alarms for a given subscription.
  Future<void> cancelSubscriptionReminders(String subscriptionId) async {
    await _notificationsPlugin.cancel(generateNotificationId(subscriptionId, 'h3'));
    await _notificationsPlugin.cancel(generateNotificationId(subscriptionId, 'h1'));
    await _notificationsPlugin.cancel(generateNotificationId(subscriptionId, 'due'));
  }
}
