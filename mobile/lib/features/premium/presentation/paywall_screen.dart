import 'package:flutter/material.dart';

class PaywallScreen extends StatelessWidget {
  final Future<void> Function(String planTier, String gateway)? onPurchase;

  const PaywallScreen({super.key, this.onPurchase});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Upgrade to Premium')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Icon(Icons.star_rounded, size: 64, color: Colors.amber),
            const SizedBox(height: 12),
            Text(
              'Unlock Full Financial Power',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              'Take full control of your wealth with zero AI leaks and private offline ledger',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[600]),
            ),
            const SizedBox(height: 24),
            _FeatureRow(icon: Icons.mic_none, title: 'Unlimited Voice Expense Logs', desc: 'No 10-request monthly limit'),
            _FeatureRow(icon: Icons.currency_exchange, title: 'Multi-Currency Valas Support', desc: 'Track USD, EUR, SGD subscriptions with custom FX confirmation'),
            _FeatureRow(icon: Icons.analytics_outlined, title: 'Advanced Leak Detector', desc: 'Customizable thresholds for Drip, Zombie & Category surges'),
            _FeatureRow(icon: Icons.sync, title: 'Real-Time Multi-Device Sync', desc: 'Seamless offline-first SQLite mirror and sync queue'),
            const SizedBox(height: 28),

            // Plan Card 1: Monthly
            Card(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: Colors.amber.shade400)),
              child: ListTile(
                title: const Text('Monthly Plan', style: TextStyle(fontWeight: FontWeight.bold)),
                subtitle: const Text('Rp 29.000 / month'),
                trailing: ElevatedButton(
                  onPressed: () => onPurchase?.call('premium_monthly', 'iap'),
                  child: const Text('Subscribe'),
                ),
              ),
            ),
            const SizedBox(height: 12),

            // Plan Card 2: Yearly (Best Value)
            Card(
              color: Colors.amber.shade50,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: const BorderSide(color: Colors.amber)),
              child: ListTile(
                title: const Text('Annual Plan (Save 30%)', style: TextStyle(fontWeight: FontWeight.bold)),
                subtitle: const Text('Rp 249.000 / year'),
                trailing: ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: Colors.amber.shade800, foregroundColor: Colors.white),
                  onPressed: () => onPurchase?.call('premium_yearly', 'iap'),
                  child: const Text('Get Annual'),
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Direct QRIS option
            OutlinedButton.icon(
              icon: const Icon(Icons.qr_code),
              label: const Text('Pay with QRIS (Instant Indonesia)'),
              onPressed: () => onPurchase?.call('premium_monthly', 'qris_web'),
              style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
            ),
          ],
        ),
      ),
    );
  }
}

class _FeatureRow extends StatelessWidget {
  final IconData icon;
  final String title;
  final String desc;

  const _FeatureRow({required this.icon, required this.title, required this.desc});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16.0),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(backgroundColor: Colors.amber.shade100, radius: 18, child: Icon(icon, color: Colors.amber.shade900, size: 20)),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                const SizedBox(height: 2),
                Text(desc, style: TextStyle(color: Colors.grey[600], fontSize: 12)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
