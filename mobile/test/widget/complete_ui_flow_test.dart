import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:personal_money_manager/features/auth/presentation/auth_screen.dart';
import 'package:personal_money_manager/features/dashboard/presentation/dashboard_screen.dart';
import 'package:personal_money_manager/features/wallets/presentation/wallets_screen.dart';
import 'package:personal_money_manager/features/transactions/presentation/transactions_screen.dart';
import 'package:personal_money_manager/features/subscriptions/presentation/subscriptions_screen.dart';
import 'package:personal_money_manager/features/leaks/presentation/leak_detector_screen.dart';
import 'package:personal_money_manager/features/premium/presentation/paywall_screen.dart';
import 'package:personal_money_manager/features/settings/presentation/settings_and_privacy_screen.dart';

void main() {
  group('10-Domain Flutter Mobile UI Tests', () {
    testWidgets('AuthScreen renders login form and switches to register', (tester) async {
      await tester.pumpWidget(const MaterialApp(home: AuthScreen()));

      expect(find.text('Welcome Back'), findsOneWidget);
      expect(find.byKey(const Key('email_field')), findsOneWidget);
      expect(find.byKey(const Key('password_field')), findsOneWidget);
      expect(find.text('Login'), findsOneWidget);

      // Tap switch to register
      await tester.tap(find.text("Don't have an account? Register"));
      await tester.pump();

      expect(find.text('Create Account'), findsOneWidget);
      expect(find.byKey(const Key('name_field')), findsOneWidget);
    });

    testWidgets('DashboardScreen renders balance and quick actions', (tester) async {
      await tester.pumpWidget(const MaterialApp(home: DashboardScreen()));

      expect(find.text('Financial Dashboard'), findsOneWidget);
      expect(find.byKey(const Key('total_balance_text')), findsOneWidget);
      expect(find.text('Voice Log'), findsOneWidget);
      expect(find.text('Add Expense'), findsOneWidget);
      expect(find.text('Transfer'), findsOneWidget);
    });

    testWidgets('WalletsScreen renders wallet accounts list', (tester) async {
      await tester.pumpWidget(const MaterialApp(home: WalletsScreen()));

      expect(find.text('My Wallets & Accounts'), findsOneWidget);
      expect(find.text('Main BCA'), findsOneWidget);
      expect(find.text('GoPay E-Wallet'), findsOneWidget);
    });

    testWidgets('TransactionsScreen renders financial ledger', (tester) async {
      await tester.pumpWidget(const MaterialApp(home: TransactionsScreen()));

      expect(find.text('Transaction Ledger'), findsOneWidget);
      expect(find.text('Lunch at Warteg'), findsOneWidget);
      expect(find.text('Monthly Salary'), findsOneWidget);
    });

    testWidgets('SubscriptionsScreen renders recurring subs', (tester) async {
      await tester.pumpWidget(const MaterialApp(home: SubscriptionsScreen()));

      expect(find.text('Subscriptions Tracker'), findsOneWidget);
      expect(find.text('Netflix Premium'), findsOneWidget);
      expect(find.text('ChatGPT Plus'), findsOneWidget);
    });

    testWidgets('LeakDetectorScreen renders advisory alerts', (tester) async {
      await tester.pumpWidget(const MaterialApp(home: LeakDetectorScreen()));

      expect(find.text('Potential Money Leaks'), findsOneWidget);
      expect(find.textContaining('Potential Money Leak: Drip Spending'), findsOneWidget);
    });

    testWidgets('PaywallScreen renders benefits and tier buttons', (tester) async {
      await tester.pumpWidget(const MaterialApp(home: PaywallScreen()));

      expect(find.text('Upgrade to Premium'), findsOneWidget);
      expect(find.text('Unlimited Voice Expense Logs'), findsOneWidget);
      expect(find.text('Monthly Plan'), findsOneWidget);
    });

    testWidgets('SettingsAndPrivacyScreen renders thresholds and GDPR', (tester) async {
      await tester.pumpWidget(const MaterialApp(home: SettingsAndPrivacyScreen()));

      expect(find.text('Settings & Privacy'), findsOneWidget);
      await tester.scrollUntilVisible(find.text('Export Transactions (CSV)'), 200);
      expect(find.text('Export Transactions (CSV)'), findsOneWidget);
      await tester.scrollUntilVisible(find.text('Delete Account Permanently (GDPR)'), 200);
      expect(find.text('Delete Account Permanently (GDPR)'), findsOneWidget);
    });
  });
}
