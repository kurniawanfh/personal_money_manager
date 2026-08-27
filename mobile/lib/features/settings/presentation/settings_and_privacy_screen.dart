import 'package:flutter/material.dart';

class SettingsAndPrivacyScreen extends StatefulWidget {
  final Map<String, dynamic> userSettings;
  final String userEmail;
  final Future<void> Function(Map<String, dynamic> updatedSettings)? onSaveSettings;
  final VoidCallback? onExportCsv;
  final VoidCallback? onExportJson;
  final Future<void> Function()? onDeleteAccount;
  final VoidCallback? onLogout;

  const SettingsAndPrivacyScreen({
    super.key,
    this.userSettings = const {
      'drip_max_single_amount': 25000.0,
      'drip_monthly_threshold': 500000.0,
      'surge_percentage_threshold': 150.0,
      'zombie_inactivity_days': 60,
      'timezone': 'Asia/Jakarta',
    },
    this.userEmail = 'user@example.com',
    this.onSaveSettings,
    this.onExportCsv,
    this.onExportJson,
    this.onDeleteAccount,
    this.onLogout,
  });

  @override
  State<SettingsAndPrivacyScreen> createState() => _SettingsAndPrivacyScreenState();
}

class _SettingsAndPrivacyScreenState extends State<SettingsAndPrivacyScreen> {
  late String _timezone;
  late double _dripMaxSingle;
  late double _dripMonthly;
  late double _surgePercentage;
  late int _zombieDays;

  @override
  void initState() {
    super.initState();
    _timezone = widget.userSettings['timezone'] ?? 'Asia/Jakarta';
    _dripMaxSingle = (widget.userSettings['drip_max_single_amount'] as num?)?.toDouble() ?? 25000.0;
    _dripMonthly = (widget.userSettings['drip_monthly_threshold'] as num?)?.toDouble() ?? 500000.0;
    _surgePercentage = (widget.userSettings['surge_percentage_threshold'] as num?)?.toDouble() ?? 150.0;
    _zombieDays = (widget.userSettings['zombie_inactivity_days'] as num?)?.toInt() ?? 60;
  }

  void _showDeleteConfirmation() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Permanent Account Deletion'),
        content: const Text(
          'Are you sure you want to permanently delete your account? All your wallets, transactions, subscriptions, and financial records will be purged immediately under GDPR.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
            onPressed: () async {
              Navigator.pop(ctx);
              await widget.onDeleteAccount?.call();
            },
            child: const Text('Delete Everything'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Settings & Privacy')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Account & Preferences', style: Theme.of(context).textTheme.titleSmall?.copyWith(color: Colors.grey[700])),
          ListTile(
            leading: const Icon(Icons.person_outline),
            title: const Text('Account Email'),
            subtitle: Text(widget.userEmail),
          ),
          ListTile(
            leading: const Icon(Icons.public),
            title: const Text('Device Timezone'),
            subtitle: Text(_timezone),
            trailing: DropdownButton<String>(
              value: _timezone,
              underline: const SizedBox(),
              items: ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC', 'America/New_York', 'Europe/London']
                  .map((tz) => DropdownMenuItem(value: tz, child: Text(tz)))
                  .toList(),
              onChanged: (val) {
                if (val != null) {
                  setState(() => _timezone = val);
                  widget.onSaveSettings?.call({'timezone': val});
                }
              },
            ),
          ),
          const Divider(),

          Text('Potential Money Leak Detector Thresholds', style: Theme.of(context).textTheme.titleSmall?.copyWith(color: Colors.grey[700])),
          ListTile(
            title: Text('Drip Max Single Amount: Rp ${_dripMaxSingle.toInt()}'),
            subtitle: Slider(
              value: _dripMaxSingle,
              min: 5000,
              max: 100000,
              divisions: 19,
              onChanged: (v) => setState(() => _dripMaxSingle = v),
              onChangeEnd: (v) => widget.onSaveSettings?.call({'drip_max_single_amount': v}),
            ),
          ),
          ListTile(
            title: Text('Drip Monthly Threshold: Rp ${_dripMonthly.toInt()}'),
            subtitle: Slider(
              value: _dripMonthly,
              min: 100000,
              max: 2000000,
              divisions: 19,
              onChanged: (v) => setState(() => _dripMonthly = v),
              onChangeEnd: (v) => widget.onSaveSettings?.call({'drip_monthly_threshold': v}),
            ),
          ),
          ListTile(
            title: Text('Category Surge Threshold: ${_surgePercentage.toInt()}%'),
            subtitle: Slider(
              value: _surgePercentage,
              min: 110,
              max: 300,
              divisions: 19,
              onChanged: (v) => setState(() => _surgePercentage = v),
              onChangeEnd: (v) => widget.onSaveSettings?.call({'surge_percentage_threshold': v}),
            ),
          ),
          ListTile(
            title: Text('Zombie Inactivity Days: $_zombieDays days'),
            subtitle: Slider(
              value: _zombieDays.toDouble(),
              min: 14,
              max: 180,
              divisions: 20,
              onChanged: (v) => setState(() => _zombieDays = v.toInt()),
              onChangeEnd: (v) => widget.onSaveSettings?.call({'zombie_inactivity_days': v.toInt()}),
            ),
          ),
          const Divider(),

          Text('Data Privacy & Exports', style: Theme.of(context).textTheme.titleSmall?.copyWith(color: Colors.grey[700])),
          ListTile(
            leading: const Icon(Icons.table_chart_outlined, color: Colors.green),
            title: const Text('Export Transactions (CSV)'),
            subtitle: const Text('Download spreadsheet of all financial logs'),
            onTap: widget.onExportCsv,
          ),
          ListTile(
            leading: const Icon(Icons.cloud_download_outlined, color: Colors.blue),
            title: const Text('Export Complete Backup (JSON)'),
            subtitle: const Text('Full account archive including wallets & subs'),
            onTap: widget.onExportJson,
          ),
          const Divider(),

          Text('Danger Zone', style: Theme.of(context).textTheme.titleSmall?.copyWith(color: Colors.red)),
          ListTile(
            leading: const Icon(Icons.delete_forever, color: Colors.red),
            title: const Text('Delete Account Permanently (GDPR)', style: TextStyle(color: Colors.red)),
            subtitle: const Text('Purge all user records permanently from database'),
            onTap: _showDeleteConfirmation,
          ),
          ListTile(
            leading: const Icon(Icons.logout),
            title: const Text('Sign Out'),
            onTap: widget.onLogout,
          ),
        ],
      ),
    );
  }
}
