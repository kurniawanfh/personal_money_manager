import 'package:flutter/material.dart';
import 'features/auth/presentation/auth_screen.dart';
import 'features/dashboard/presentation/dashboard_screen.dart';
import 'features/wallets/presentation/wallets_screen.dart';
import 'features/transactions/presentation/transactions_screen.dart';
import 'features/subscriptions/presentation/subscriptions_screen.dart';
import 'features/subscriptions/presentation/planned_expenses_screen.dart';
import 'features/voice/presentation/voice_bottom_sheet.dart';
import 'features/leaks/presentation/leak_detector_screen.dart';
import 'features/premium/presentation/paywall_screen.dart';
import 'features/settings/presentation/settings_and_privacy_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const PersonalMoneyManagerApp());
}

class PersonalMoneyManagerApp extends StatelessWidget {
  const PersonalMoneyManagerApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Personal Money Manager',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.indigo),
        useMaterial3: true,
      ),
      home: const MainNavigationHost(),
      routes: {
        '/auth': (context) => const AuthScreen(),
        '/paywall': (context) => const PaywallScreen(),
        '/planned-expenses': (context) => const PlannedExpensesScreen(),
        '/leaks': (context) => const LeakDetectorScreen(),
        '/settings': (context) => const SettingsAndPrivacyScreen(),
      },
    );
  }
}

class MainNavigationHost extends StatefulWidget {
  const MainNavigationHost({super.key});

  @override
  State<MainNavigationHost> createState() => _MainNavigationHostState();
}

class _MainNavigationHostState extends State<MainNavigationHost> {
  int _currentIndex = 0;

  final List<Widget> _screens = [
    const DashboardScreen(),
    const WalletsScreen(),
    const TransactionsScreen(),
    const SubscriptionsScreen(),
    const SettingsAndPrivacyScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (index) => setState(() => _currentIndex = index),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'Dashboard'),
          NavigationDestination(icon: Icon(Icons.account_balance_wallet_outlined), selectedIcon: Icon(Icons.account_balance_wallet), label: 'Wallets'),
          NavigationDestination(icon: Icon(Icons.receipt_long_outlined), selectedIcon: Icon(Icons.receipt_long), label: 'Ledger'),
          NavigationDestination(icon: Icon(Icons.repeat), selectedIcon: Icon(Icons.repeat_on), label: 'Subs'),
          NavigationDestination(icon: Icon(Icons.settings_outlined), selectedIcon: Icon(Icons.settings), label: 'Settings'),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {
          showModalBottomSheet(
            context: context,
            isScrollControlled: true,
            shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
            builder: (_) => const VoiceBottomSheet(),
          );
        },
        child: const Icon(Icons.mic),
      ),
    );
  }
}
