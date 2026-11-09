import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

class PushService {
  static StreamSubscription<String>? _refreshSubscription;
  static StreamSubscription<RemoteMessage>? _foregroundSubscription;
  static bool _initialized = false;

  static Future<void> configure({
    required String apiBase,
    required String accessToken,
    required void Function(RemoteMessage message) onMessage,
  }) async {
    try {
      if (!_initialized) {
        await Firebase.initializeApp();
        _initialized = true;
      }

      final messaging = FirebaseMessaging.instance;
      final permission = await messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
        provisional: false,
      );
      if (permission.authorizationStatus == AuthorizationStatus.denied) {
        debugPrint('Elzoco push permission denied by user.');
        return;
      }

      final token = await messaging.getToken();
      if (token != null && token.isNotEmpty) {
        await _register(apiBase, accessToken, token);
      }

      await _refreshSubscription?.cancel();
      _refreshSubscription = messaging.onTokenRefresh.listen((nextToken) {
        _register(apiBase, accessToken, nextToken);
      });

      await _foregroundSubscription?.cancel();
      _foregroundSubscription = FirebaseMessaging.onMessage.listen(onMessage);
    } catch (error, stack) {
      // Firebase configuration is supplied per app/environment. Missing config
      // must never prevent the HRM application from opening or signing in.
      debugPrint('Elzoco push is not configured yet: $error');
      debugPrintStack(stackTrace: stack);
    }
  }

  static Future<void> _register(
    String apiBase,
    String accessToken,
    String pushToken,
  ) async {
    final uri = Uri.parse(
      apiBase.replaceAll(RegExp(r'/+$'), '') +
          '/api/mobile.php?action=push_token_register',
    );
    final response = await http
        .post(
          uri,
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Authorization': 'Bearer $accessToken',
          },
          body: jsonEncode({
            'action': 'push_token_register',
            'token': pushToken,
            'platform': Platform.isIOS ? 'ios' : 'android',
          }),
        )
        .timeout(const Duration(seconds: 20));

    if (response.statusCode < 200 || response.statusCode >= 300) {
      debugPrint('Elzoco push token registration failed: HTTP ${response.statusCode}');
    }
  }

  static Future<void> unregister({
    required String apiBase,
    required String accessToken,
  }) async {
    try {
      final messaging = FirebaseMessaging.instance;
      final token = await messaging.getToken();
      if (token == null || token.isEmpty) return;
      final uri = Uri.parse(
        apiBase.replaceAll(RegExp(r'/+$'), '') +
            '/api/mobile.php?action=push_token_unregister',
      );
      await http
          .post(
            uri,
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'Authorization': 'Bearer $accessToken',
            },
            body: jsonEncode({
              'action': 'push_token_unregister',
              'token': token,
            }),
          )
          .timeout(const Duration(seconds: 10));
    } catch (error) {
      debugPrint('Elzoco push token unregister skipped: $error');
    }
  }
}
