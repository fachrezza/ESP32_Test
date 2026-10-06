/// Status satu mesin, sesuai payload `mesin_{nomor}` dari API.
class Mesin {
  final int nomor;
  final bool status;
  final int timerSec;

  const Mesin({required this.nomor, required this.status, required this.timerSec});

  String get durasi {
    final h = (timerSec ~/ 3600).toString().padLeft(2, '0');
    final m = (timerSec % 3600 ~/ 60).toString().padLeft(2, '0');
    final s = (timerSec % 60).toString().padLeft(2, '0');
    return '$h:$m:$s';
  }
}

/// Snapshot seluruh mesin dari GET /api/kontrol-mesin.
class StatusMesin {
  final int? activeMachine;
  final List<Mesin> mesins;

  const StatusMesin({required this.activeMachine, required this.mesins});

  factory StatusMesin.fromJson(Map<String, dynamic> json) {
    final mesins = <Mesin>[];

    json.forEach((key, value) {
      if (key.startsWith('mesin_') && value is Map<String, dynamic>) {
        mesins.add(Mesin(
          nomor: int.parse(key.substring('mesin_'.length)),
          status: value['status'] == true,
          timerSec: (value['timer_sec'] as num?)?.toInt() ?? 0,
        ));
      }
    });

    mesins.sort((a, b) => a.nomor.compareTo(b.nomor));

    return StatusMesin(
      activeMachine: (json['active_machine'] as num?)?.toInt(),
      mesins: mesins,
    );
  }
}
