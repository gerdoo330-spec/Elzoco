import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:elzoco_hrm/main.dart';

void main() {
  testWidgets('role selection screen renders employee and manager choices', (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: RoleSelection(onSelect: (_) {}),
      ),
    ));

    expect(find.text('ELZOCO'), findsOneWidget);
    expect(find.text('به الزوکو خوش آمدید'), findsOneWidget);
    expect(find.text('کارمند'), findsOneWidget);
    expect(find.text('مدیر و سرپرست'), findsOneWidget);
    expect(find.text('برای ورود، نوع حساب کاربری خود را انتخاب کنید'), findsOneWidget);
  });

  testWidgets('selecting a role invokes the role callback', (tester) async {
    String? selected;
    await tester.pumpWidget(MaterialApp(
      home: Directionality(
        textDirection: TextDirection.rtl,
        child: RoleSelection(onSelect: (kind) => selected = kind),
      ),
    ));

    await tester.tap(find.text('کارمند'));
    expect(selected, 'employee');
    await tester.tap(find.text('مدیر و سرپرست'));
    expect(selected, 'manager');
  });
}
