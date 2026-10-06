import 'dart:async';

import 'package:flutter/material.dart';

import '../models/mesin.dart';
import '../services/mesin_api.dart';
import '../widgets/mesin_card.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  static const _pollInterval = Duration(seconds: 1);

  final _api = MesinApi();
  Timer? _timer;
  bool _fetching = false;

  StatusMesin? _data;
  String? _error;
  DateTime? _updatedAt;
  int? _busyNomor;

  @override
  void initState() {
    super.initState();
    _refresh();
    _timer = Timer.periodic(_pollInterval, (_) => _refresh());
  }

  @override
  void dispose() {
    _timer?.cancel();
    _api.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    // Lewati tick kalau request sebelumnya belum selesai
    if (_fetching) return;
    _fetching = true;

    try {
      final data = await _api.fetchStatus();
      if (!mounted) return;
      setState(() {
        _data = data;
        _error = null;
        _updatedAt = DateTime.now();
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = 'Tidak dapat terhubung ke server: $e');
    } finally {
      _fetching = false;
    }
  }

  Future<void> _toggle(Mesin mesin) async {
    setState(() => _busyNomor = mesin.nomor);
    final messenger = ScaffoldMessenger.of(context);

    try {
      final message = await _api.setStatus(mesin.nomor, nyala: !mesin.status);
      messenger.showSnackBar(SnackBar(content: Text(message)));
    } catch (e) {
      messenger.showSnackBar(
        SnackBar(content: Text('$e'), backgroundColor: Colors.red.shade700),
      );
    } finally {
      if (mounted) setState(() => _busyNomor = null);
      _refresh();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Dashboard Mesin')),
      body: RefreshIndicator(onRefresh: _refresh, child: _buildBody()),
    );
  }

  Widget _buildBody() {
    final data = _data;

    if (data == null) {
      return ListView(
        children: [
          const SizedBox(height: 160),
          Center(
            child: _error == null
                ? const CircularProgressIndicator()
                : Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(_error!, textAlign: TextAlign.center),
                  ),
          ),
        ],
      );
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (_error != null) _ErrorBanner(message: _error!),
        _SummaryCard(activeMachine: data.activeMachine, updatedAt: _updatedAt),
        const SizedBox(height: 8),
        if (data.mesins.isEmpty)
          const Padding(
            padding: EdgeInsets.all(24),
            child: Center(child: Text('Belum ada data mesin.')),
          ),
        for (final mesin in data.mesins)
          MesinCard(
            key: ValueKey(mesin.nomor),
            mesin: mesin,
            busy: _busyNomor == mesin.nomor,
            onToggle: _busyNomor == null ? () => _toggle(mesin) : null,
          ),
      ],
    );
  }
}

class _SummaryCard extends StatelessWidget {
  final int? activeMachine;
  final DateTime? updatedAt;

  const _SummaryCard({required this.activeMachine, required this.updatedAt});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final t = updatedAt;
    final jam = t == null
        ? '-'
        : [t.hour, t.minute, t.second].map((v) => v.toString().padLeft(2, '0')).join(':');

    return Card(
      color: theme.colorScheme.primaryContainer,
      child: ListTile(
        leading: Icon(
          activeMachine != null ? Icons.bolt : Icons.power_off,
          color: theme.colorScheme.onPrimaryContainer,
        ),
        title: const Text('Mesin aktif'),
        subtitle: Text(
          activeMachine != null ? 'Mesin $activeMachine' : 'Tidak ada',
          style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
        ),
        trailing: Text(
          'Diperbarui\n$jam',
          textAlign: TextAlign.right,
          style: theme.textTheme.bodySmall,
        ),
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  final String message;

  const _ErrorBanner({required this.message});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: Theme.of(context).colorScheme.errorContainer,
      child: ListTile(
        leading: const Icon(Icons.wifi_off),
        title: Text(message, style: Theme.of(context).textTheme.bodySmall),
      ),
    );
  }
}
