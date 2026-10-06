import 'package:flutter_test/flutter_test.dart';

import 'package:frontend/models/mesin.dart';

void main() {
  test('StatusMesin.fromJson membaca payload API', () {
    final status = StatusMesin.fromJson({
      'active_machine': 2,
      'mesin_2': {'status': true, 'timer_sec': 3725},
      'mesin_1': {'status': false, 'timer_sec': 25},
    });

    expect(status.activeMachine, 2);
    expect(status.mesins.map((m) => m.nomor), [1, 2]);
    expect(status.mesins[1].status, isTrue);
    expect(status.mesins[1].durasi, '01:02:05');
  });

  test('active_machine null saat semua mesin mati', () {
    final status = StatusMesin.fromJson({
      'active_machine': null,
      'mesin_1': {'status': false, 'timer_sec': 0},
    });

    expect(status.activeMachine, isNull);
  });
}
