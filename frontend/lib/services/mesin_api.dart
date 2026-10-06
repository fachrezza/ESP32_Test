import 'dart:convert';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:http/http.dart' as http;

import '../models/mesin.dart';

class ApiException implements Exception {
  final String message;
  const ApiException(this.message);

  @override
  String toString() => message;
}

class MesinApi {
  /// Ganti lewat `flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8000`.
  /// Default: localhost untuk web (Chrome), 10.0.2.2 (= localhost komputer) untuk emulator Android.
  static const _envBaseUrl = String.fromEnvironment('API_BASE_URL');

  static String get baseUrl => _envBaseUrl.isNotEmpty
      ? _envBaseUrl
      : (kIsWeb ? 'http://localhost:8000' : 'http://10.0.2.2:8000');

  static const _timeout = Duration(seconds: 5);
  static const _headers = {'Accept': 'application/json'};

  final http.Client _client;

  MesinApi({http.Client? client}) : _client = client ?? http.Client();

  Uri _uri(String path) => Uri.parse('$baseUrl/api/kontrol-mesin$path');

  Future<StatusMesin> fetchStatus() async {
    final res = await _client.get(_uri(''), headers: _headers).timeout(_timeout);

    if (res.statusCode != 200) {
      throw ApiException('Gagal memuat data (HTTP ${res.statusCode})');
    }

    return StatusMesin.fromJson(jsonDecode(res.body) as Map<String, dynamic>);
  }

  /// POST /{nomor}/on atau /{nomor}/off. Mengembalikan pesan dari server.
  Future<String> setStatus(int nomor, {required bool nyala}) async {
    final aksi = nyala ? 'on' : 'off';
    final res = await _client.post(_uri('/$nomor/$aksi'), headers: _headers).timeout(_timeout);

    final body = jsonDecode(res.body) as Map<String, dynamic>;
    final message = body['message'] as String? ?? 'HTTP ${res.statusCode}';

    if (res.statusCode != 200) {
      throw ApiException(message);
    }

    return message;
  }

  void dispose() => _client.close();
}
