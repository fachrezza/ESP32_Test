import 'package:flutter/material.dart';

import '../models/mesin.dart';

class MesinCard extends StatelessWidget {
  final Mesin mesin;
  final bool busy;
  final VoidCallback? onToggle;

  const MesinCard({super.key, required this.mesin, required this.busy, this.onToggle});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final on = mesin.status;
    final accent = on ? Colors.green.shade600 : theme.colorScheme.outline;

    return Card(
      clipBehavior: Clip.antiAlias,
      child: Container(
        decoration: BoxDecoration(border: Border(left: BorderSide(color: accent, width: 6))),
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.precision_manufacturing, color: accent),
                const SizedBox(width: 8),
                Text('Mesin ${mesin.nomor}', style: theme.textTheme.titleLarge),
                const Spacer(),
                _StatusBadge(on: on),
              ],
            ),
            const SizedBox(height: 16),
            Text(
              mesin.durasi,
              style: theme.textTheme.displaySmall?.copyWith(
                fontWeight: FontWeight.bold,
                fontFeatures: const [FontFeature.tabularFigures()],
                color: on ? null : theme.colorScheme.outline,
              ),
            ),
            Text(on ? 'Durasi berjalan' : 'Durasi terakhir', style: theme.textTheme.bodySmall),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: busy ? null : onToggle,
                style: FilledButton.styleFrom(
                  backgroundColor: on ? Colors.red.shade600 : Colors.green.shade600,
                ),
                icon: busy
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : Icon(on ? Icons.power_settings_new : Icons.play_arrow),
                label: Text(on ? 'Matikan' : 'Nyalakan'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final bool on;

  const _StatusBadge({required this.on});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: on ? Colors.green.shade50 : Colors.grey.shade200,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.circle, size: 8, color: on ? Colors.green.shade600 : Colors.grey),
          const SizedBox(width: 6),
          Text(
            on ? 'MENYALA' : 'MATI',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.bold,
              color: on ? Colors.green.shade800 : Colors.grey.shade700,
            ),
          ),
        ],
      ),
    );
  }
}
