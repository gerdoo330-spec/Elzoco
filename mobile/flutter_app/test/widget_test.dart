import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:elzoco_hrm/main.dart';

void main() {
  testWidgets('employee and manager login screen renders', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: Login(onLogin: (_, __) {}),
      ),
    ));
    expect(find.text('ELZOCO'), findsOneWidget);
    expect(find.text('سامانه منابع انسانی'), findsOneWidget);
    expect(find.text('ورود به حساب کاربری'), findsOneWidget);
  });
}
