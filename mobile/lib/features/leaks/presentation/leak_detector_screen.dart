import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class LeakDetectorScreen extends StatelessWidget {
  final Map<String, dynamic> analysisData;
  final VoidCallback? onRefresh;

  const LeakDetectorScreen({
    super.key,
    this.analysisData = const {
      'summary': {
        'total_leaks_detected': 3,
        'potential_monthly_savings': 625000.0,
      },
      'alerts': [
        {
          'rule_key': 'drip_micro_spending',
          'severity': 'warning',
          'title': 'Potential Money Leak: Drip Spending Accumulation',
          'description': 'You accumulated Rp 520.000 across 28 small micro-purchases under Rp 25.000 this month.',
          'recommendation': 'Consider consolidating snacks and convenience store runs into weekly planned groceries.',
        },
        {
          'rule_key': 'zombie_subscription',
          'severity': 'warning',
          'title': 'Potential Money Leak: Inactive Zombie Subscription',
          'description': "Subscription 'Gym Membership' (Rp 350.000/mo) has had zero attendance activity for 64 days.",
          'recommendation': 'Pause or cancel this gym membership if you are not utilizing the facility.',
        },
        {
          'rule_key': 'overlapping_subscriptions',
          'severity': 'info',
          'title': 'Potential Money Leak: Overlapping Subscriptions in Streaming',
          'description': 'You have 3 active streaming video services (Netflix, Disney+, HBO) totaling Rp 375.000/mo.',
          'recommendation': 'Rotate subscriptions monthly depending on which shows you are currently watching.',
        },
      ],
    },
    this.onRefresh,
  });

  String _formatCurrency(double amount) {
    final formatter = NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0);
    return formatter.format(amount);
  }

  @override
  Widget build(BuildContext context) {
    final summary = analysisData['summary'] ?? {};
    final List alerts = analysisData['alerts'] ?? [];
    final savings = (summary['potential_monthly_savings'] as num?)?.toDouble() ?? 0.0;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Potential Money Leaks'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: onRefresh),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Savings Overview Card
            Card(
              color: Colors.orange.shade50,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              child: Padding(
                padding: const EdgeInsets.all(20.0),
                child: Column(
                  children: [
                    const Icon(Icons.savings_outlined, color: Colors.orange, size: 40),
                    const SizedBox(height: 8),
                    const Text('Estimated Monthly Savings Potential', style: TextStyle(fontSize: 13, color: Colors.brown)),
                    const SizedBox(height: 4),
                    Text(
                      _formatCurrency(savings),
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 24, color: Colors.deepOrange),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Based on ${alerts.length} non-judgmental advisory leak analyses',
                      style: TextStyle(fontSize: 11, color: Colors.grey[700]),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            Text('Detected Advisory Alerts', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),
            if (alerts.isEmpty)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(32.0),
                  child: Text('No potential money leaks detected this month! 🎉'),
                ),
              )
            else
              ...alerts.map((alert) {
                final isWarning = alert['severity'] == 'warning';
                final color = isWarning ? Colors.amber.shade900 : Colors.blue.shade800;

                return Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(isWarning ? Icons.warning_amber_rounded : Icons.info_outline, color: color, size: 20),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                alert['title'] ?? 'Potential Money Leak',
                                style: TextStyle(fontWeight: FontWeight.bold, color: color, fontSize: 14),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(alert['description'] ?? '', style: const TextStyle(fontSize: 13)),
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: Colors.grey.shade100,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(Icons.lightbulb_outline, size: 16, color: Colors.indigo),
                              const SizedBox(width: 6),
                              Expanded(
                                child: Text(
                                  alert['recommendation'] ?? '',
                                  style: const TextStyle(fontSize: 12, color: Colors.indigo, fontStyle: FontStyle.italic),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }
}
