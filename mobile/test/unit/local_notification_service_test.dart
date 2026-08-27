import 'package:flutter_test/flutter_test.dart';
import 'package:personal_money_manager/core/notifications/local_notification_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('LocalNotificationService Unit Tests', () {
    late LocalNotificationService notificationService;

    setUp(() {
      notificationService = LocalNotificationService();
      notificationService.initTimezoneOnly();
    });

    test('generate deterministic integer notification ID', () {
      final id1 = notificationService.generateNotificationId('sub-123', 'h3');
      final id2 = notificationService.generateNotificationId('sub-123', 'h3');
      final id3 = notificationService.generateNotificationId('sub-123', 'h1');

      expect(id1, equals(id2));
      expect(id1, isNot(equals(id3)));
      expect(id1, isPositive);
    });

    test('calculate reminder date for H-3 and H-1', () {
      final billingDate = DateTime(2026, 9, 15);
      final h3Date = notificationService.calculateReminderDate(
        billingDate: billingDate,
        daysBefore: 3,
        targetHour: 9,
      );

      expect(h3Date.day, equals(12));
      expect(h3Date.month, equals(9));
      expect(h3Date.hour, equals(9));

      final h1Date = notificationService.calculateReminderDate(
        billingDate: billingDate,
        daysBefore: 1,
        targetHour: 9,
      );

      expect(h1Date.day, equals(14));
      expect(h1Date.month, equals(9));
    });
  });
}
