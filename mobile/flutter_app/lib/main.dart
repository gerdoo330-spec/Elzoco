import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';
import 'package:http/http.dart' as http;
import 'package:file_picker/file_picker.dart';
import 'services/push_service.dart';

const apiBase=String.fromEnvironment('API_BASE_URL',defaultValue:'https://hrsarv.ir/payroll');
const vault=FlutterSecureStorage();
const ink=Color(0xFFF5F7FB),navy=Color(0xFF0B1220),blue=Color(0xFF4F8DFF),cyan=Color(0xFF77B7F7),mint=Color(0xFF35C99A),gold=Color(0xFFFF9B45),bg=Color(0xFF0B1220),surface=Color(0xFF121D2E),muted=Color(0xFF9AA9BC),line=Color(0xFF26364B),red=Color(0xFFFF6474);
void main(){WidgetsFlutterBinding.ensureInitialized();SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(statusBarColor:Colors.transparent,statusBarIconBrightness:Brightness.light,systemNavigationBarColor:bg,systemNavigationBarIconBrightness:Brightness.light));runApp(const ElzocoApp());}
Map<String,dynamic> m(dynamic x)=>x is Map?Map<String,dynamic>.from(x):<String,dynamic>{};
List<dynamic> l(dynamic x)=>x is List?x:<dynamic>[];
String fa(dynamic x){
 final raw=x is num?x:num.tryParse((x?.toString()??'').replaceAll(',','').replaceAll('٬','').replaceAll('٫','.').trim())??0;
 return '\u2066'+raw.round().toString().replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'),(z)=>',')+'\u2069';
}
String money(dynamic x)=>fa(x)+' ریال';
// مقادیر عددی و شناسه‌ها مستقل از راست‌چین بودن رابط فارسی، چپ‌به‌راست نمایش داده می‌شوند.
Widget ltrValue(String value,{TextStyle? style,TextAlign align=TextAlign.left})=>Directionality(textDirection:TextDirection.ltr,child:Text(value,style:style,textAlign:align));
bool _containsPersian(String value)=>RegExp(r'[\u0600-\u06FF]').hasMatch(value);
Widget valueText(dynamic value,{TextStyle? style}){final v=(value==null||value.toString().isEmpty)?'—':value.toString();return _containsPersian(v)?Text(v,style:style):ltrValue(v,style:style);}

String stage(dynamic x)=>({'direct':'در انتظار سرپرست','general':'در انتظار مدیر','admin':'در انتظار منابع انسانی','evaluated':'در انتظار تصمیم مدیر','final':'تأیید نهایی','rejected':'رد شده'}[x?.toString()]??x?.toString()??'در حال بررسی');
String roleText(dynamic x)=>({'admin':'مدیر سامانه','senior':'مدیر ارشد','manager':'مدیر / سرپرست','supervisor':'سرپرست','supervisor_manager':'سوپروایزر'}[x?.toString()]??'مدیر');
const months=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];


class Icon3D extends StatelessWidget {
 final IconData icon; final Color color; final double size; final bool compact;
 const Icon3D(this.icon,{super.key,required this.color,this.size=42,this.compact=false});
 @override Widget build(BuildContext context){
  final radius=BorderRadius.circular(size*.27);
  return Container(
   width:size,height:size,
   decoration:BoxDecoration(
    color:Color.lerp(color,Colors.white,.90),
    borderRadius:radius,
    border:Border.all(color:color.withOpacity(.10)),
   ),
   alignment:Alignment.center,
   child:Icon(icon,color:color,size:size*.49),
  );
 }
}
class MobileApi{
 static String get root=>apiBase.replaceAll(RegExp(r'/+$'),'');
 static Uri url(String a)=>Uri.parse(root+'/api/mobile.php?action='+a);
 static Future<Map<String,dynamic>> call(String a,{String method='GET',String? token,Map<String,dynamic>? body})async{
  final h={'Accept':'application/json','Content-Type':'application/json',if(token!=null)'Authorization':'Bearer '+token};
  final Future<http.Response> request = method=='GET'
    ? http.get(url(a),headers:h)
    : http.post(url(a),headers:h,body:jsonEncode({...?body,'action':a}));
  // Do not leave the login screen waiting forever when the host/API is unreachable.
  final r = await request.timeout(
    const Duration(seconds:25),
    onTimeout: () => throw Exception('پاسخی از سرور دریافت نشد. اتصال اینترنت و نشانی سامانه را بررسی کنید و دوباره تلاش کنید.'),
  );
  final d=jsonDecode(utf8.decode(r.bodyBytes));if(d is! Map<String,dynamic>)throw Exception('پاسخ سرور معتبر نیست.');
  if(r.statusCode==401||d['logout']==true)throw Exception('نشست شما منقضی شده است؛ دوباره وارد شوید.');
  if(r.statusCode<200||r.statusCode>=300||d['ok']==false)throw Exception(d['message']??'عملیات انجام نشد.');
  return d;
 }
 static Future<Map<String,dynamic>> upload(String token,PlatformFile file,int year,int month)async{
  if(file.bytes==null)throw Exception('خواندن فایل انتخاب‌شده ممکن نیست.');
  final q=http.MultipartRequest('POST',url('mobile_upload'))..headers.addAll({'Accept':'application/json','Authorization':'Bearer '+token})..fields.addAll({'year':'$year','month':'$month'});
  q.files.add(http.MultipartFile.fromBytes('file',file.bytes!,filename:file.name));
  final sr=await q.send().timeout(const Duration(minutes:4));final r=await http.Response.fromStream(sr);final d=jsonDecode(utf8.decode(r.bodyBytes));
  if(d is! Map<String,dynamic>||r.statusCode<200||r.statusCode>=300||d['ok']==false)throw Exception(d is Map?d['message']??'بارگذاری ناموفق بود.':'پاسخ سرور معتبر نیست.');
  return d;
 }
}
class AppAppearance extends ChangeNotifier {
  static const palettes = <String, Color>{
    'midnight': Color(0xFF4F8DFF),
    'copper': Color(0xFFE8873A),
    'green': Color(0xFF28A879),
    'silver': Color(0xFF9AA8B8),
  };
  String palette = 'midnight';
  ThemeMode mode = ThemeMode.dark;
  Color get accent => palettes[palette] ?? palettes['midnight']!;
  Future<void> load() async {
    palette = await vault.read(key: 'ui_palette') ?? 'midnight';
    // Migrate the former default once, while preserving future user palette choices.
    final migrated = await vault.read(key: 'ui_midnight_migrated') == 'true';
    if (!migrated && palette == 'copper') palette = 'midnight';
    if (!palettes.containsKey(palette)) palette = 'midnight';
    await vault.write(key: 'ui_palette', value: palette);
    await vault.write(key: 'ui_midnight_migrated', value: 'true');
    mode = ThemeMode.dark;
    await vault.write(key: 'ui_theme_mode', value: 'dark');
    SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: mode == ThemeMode.dark ? Brightness.light : Brightness.dark,
      systemNavigationBarColor: mode == ThemeMode.dark ? bg : const Color(0xFFF3F4F6),
      systemNavigationBarIconBrightness: mode == ThemeMode.dark ? Brightness.light : Brightness.dark,
    ));
    notifyListeners();
  }
  Future<void> setPalette(String value) async {
    if (!palettes.containsKey(value)) return;
    palette = value;
    await vault.write(key: 'ui_palette', value: value);
    notifyListeners();
  }
  Future<void> setMode(ThemeMode value) async {
    // نسخه موبایل فعلاً از یک پوسته‌ی یکپارچه‌ی تیره استفاده می‌کند تا کنتراست
    // تمام صفحه‌های بومی و اجزای سفارشی یکسان و قابل پیش‌بینی بماند.
    mode = ThemeMode.dark;
    await vault.write(key: 'ui_theme_mode', value: 'dark');
    SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.light,
      systemNavigationBarColor: bg,
      systemNavigationBarIconBrightness: Brightness.light,
    ));
    notifyListeners();
  }
}
final appearance = AppAppearance();

ThemeData buildTheme(Brightness brightness) {
  final dark = brightness == Brightness.dark;
  final accent = appearance.accent;
  final scheme = ColorScheme.fromSeed(seedColor: accent, brightness: brightness,
    surface: dark ? const Color(0xFF121D2E) : const Color(0xFFFFFFFF));
  return ThemeData(
    useMaterial3: true, brightness: brightness, colorScheme: scheme,
    scaffoldBackgroundColor: dark ? const Color(0xFF0B1220) : const Color(0xFFF4F6FA),
    appBarTheme: AppBarTheme(backgroundColor: dark ? const Color(0xFF0B1220) : const Color(0xFFF4F6FA),
      foregroundColor: dark ? ink : const Color(0xFF20242A), elevation: 0, scrolledUnderElevation: 0),
    inputDecorationTheme: InputDecorationTheme(
      filled: true, fillColor: dark ? const Color(0xFF121D2E) : Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 17),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(17), borderSide: BorderSide(color: dark ? line : const Color(0xFFD1D5DB))),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(17), borderSide: BorderSide(color: dark ? line : const Color(0xFFDCE2E9))),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(17), borderSide: BorderSide(color: accent, width: 1.6))),
    filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48),
      backgroundColor: accent, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
      textStyle: const TextStyle(fontWeight: FontWeight.w800))),
    snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
  );
}
class ElzocoApp extends StatefulWidget {
  const ElzocoApp({super.key});
  @override State<ElzocoApp> createState() => _ElzocoAppState();
}
class _ElzocoAppState extends State<ElzocoApp> {
  @override void initState() { super.initState(); appearance.load(); }
  @override Widget build(BuildContext c) => AnimatedBuilder(
    animation: appearance,
    builder: (_, __) => MaterialApp(
      debugShowCheckedModeBanner: false, title: 'ELZOCO HRM',
      theme: buildTheme(Brightness.light), darkTheme: buildTheme(Brightness.dark), themeMode: appearance.mode,
      locale: const Locale('fa'), supportedLocales: const [Locale('fa'), Locale('en')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      localeResolutionCallback: (deviceLocale, supported) => const Locale('fa'),
      builder: (context, child) => Directionality(textDirection: TextDirection.rtl, child: child ?? const SizedBox.shrink()),
      home: const Gate()));
}
class Gate extends StatefulWidget {
  const Gate({super.key});
  @override State<Gate> createState() => _GateState();
}
class _GateState extends State<Gate> {
  final LocalAuthentication auth = LocalAuthentication();
  String? token, selectedKind, savedToken;
  Map<String,dynamic>? data, savedData;
  bool busy = true, authenticating = false;
  String? error;

  String get tokenKey => 'access_token_' + (selectedKind ?? 'employee');
  String get biometricKey => 'biometric_enabled_' + (selectedKind ?? 'employee');

  @override void initState() { super.initState(); restore(); }

  Future<void> restore() async {
    // Migrate an existing installation's single token without changing its account.
    try {
      final legacy = await vault.read(key: 'access_token');
      if (legacy != null && legacy.isNotEmpty) {
        final d = await MobileApi.call('me', token: legacy);
        final u = m(d['user']);
        final kind = u['type'] == 'employee' ? 'employee' : 'manager';
        await vault.write(key: 'access_token_' + kind, value: legacy);
        final enabled = await vault.read(key: 'biometric_enabled') == 'true';
        await vault.write(key: 'biometric_enabled_' + kind, value: enabled ? 'true' : 'false');
        await vault.delete(key: 'access_token');
        await vault.delete(key: 'biometric_enabled');
      }
    } catch (_) {
      await vault.delete(key: 'access_token');
      await vault.delete(key: 'biometric_enabled');
    }
    if (mounted) setState(() => busy = false);
  }

  Future<void> chooseRole(String kind) async {
    if (busy) return;
    setState(() { busy = true; selectedKind = kind; error = null; savedToken = null; savedData = null; });
    try {
      final t = await vault.read(key: 'access_token_' + kind);
      final enabled = await vault.read(key: 'biometric_enabled_' + kind) == 'true';
      if (t != null && t.isNotEmpty) {
        final d = await MobileApi.call('me', token: t);
        final u = m(d['user']);
        final correct = kind == 'employee' ? u['type'] == 'employee' : u['type'] == 'admin';
        if (!correct) throw Exception('نوع حساب ذخیره‌شده با نقش انتخابی مطابقت ندارد.');
        if (enabled && await auth.isDeviceSupported() && await auth.canCheckBiometrics) {
          savedToken = t;
          savedData = d;
        } else {
          await vault.write(key: 'biometric_enabled_' + kind, value: 'false');
        }
      }
    } catch (_) {
      await vault.delete(key: 'access_token_' + kind);
      await vault.delete(key: 'biometric_enabled_' + kind);
      savedToken = null;
      savedData = null;
    }
    if (mounted) setState(() => busy = false);
  }

  Future<void> biometricLogin() async {
    final t = savedToken;
    if (t == null || authenticating) return;
    setState(() => authenticating = true);
    try {
      final ok = await auth.authenticate(
        localizedReason: 'برای ورود به پنل ' + (selectedKind == 'employee' ? 'کارمند' : 'مدیر') + ' هویت خود را تأیید کنید',
        options: const AuthenticationOptions(biometricOnly: true, stickyAuth: true));
      if (ok) {
        final d = await MobileApi.call('me', token: t);
        final u = m(d['user']);
        final correct = selectedKind == 'employee' ? u['type'] == 'employee' : u['type'] == 'admin';
        if (!correct) throw Exception('حساب کاربری با نقش انتخابی مطابقت ندارد.');
        if (mounted) setState(() { token = t; data = d; savedToken = null; savedData = null; });
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('احراز هویت انجام نشد: ' + e.toString())));
    } finally { if (mounted) setState(() => authenticating = false); }
  }

  void logged(String t, Map<String,dynamic> d) {
    setState(() { token = t; data = d; savedToken = null; savedData = null; });
  }

  void logout() {
    setState(() { token = null; data = null; selectedKind = null; savedToken = null; savedData = null; error = null; });
  }

  @override Widget build(BuildContext c) {
    if (busy) return const Scaffold(body: Center(child: CircularProgressIndicator()));
    if (token != null) return Shell(token: token!, initial: data!, onLogout: logout);
    if (selectedKind == null) return RoleSelection(onSelect: chooseRole);
    if (savedToken != null && savedData != null) return BiometricGate(
      user: m(savedData!['user']), busy: authenticating, onAuthenticate: biometricLogin,
      onUsePassword: () => setState(() { savedToken = null; savedData = null; }));
    return Login(kind: selectedKind!, onBack: () => setState(() { selectedKind = null; }),
      onLogin: logged);
  }
}

class RoleSelection extends StatelessWidget {
  final ValueChanged<String> onSelect;
  const RoleSelection({super.key, required this.onSelect});

  Widget roleCard(BuildContext context, String title, String subtitle, String kind, IconData icon, Color accent) {
    return Material(
      color: const Color(0xFF142238),
      borderRadius: BorderRadius.circular(25),
      child: InkWell(
        borderRadius: BorderRadius.circular(25),
        onTap: () => onSelect(kind),
        child: Container(
          padding: const EdgeInsets.all(21),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(25),
            border: Border.all(color: accent.withOpacity(.35)),
            gradient: LinearGradient(begin: Alignment.topRight, end: Alignment.bottomLeft,
              colors: [accent.withOpacity(.15), const Color(0xFF142238), const Color(0xFF101B2D)])),
          child: Row(children: [
            Container(width: 58, height: 58, decoration: BoxDecoration(
              color: accent.withOpacity(.16), borderRadius: BorderRadius.circular(19)),
              child: Icon(icon, color: accent, size: 30)),
            const SizedBox(width: 16),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: const TextStyle(color: ink, fontSize: 17, fontWeight: FontWeight.w900)),
              const SizedBox(height: 5),
              Text(subtitle, style: const TextStyle(color: muted, fontSize: 11)),
            ])),
            Icon(Icons.arrow_back_ios_new_rounded, color: accent, size: 17),
          ]),
        ),
      ),
    );
  }

  @override Widget build(BuildContext context) => Scaffold(
    backgroundColor: bg,
    body: Stack(children: [
      Positioned(top: -100, left: -100, child: Container(width: 290, height: 290,
        decoration: BoxDecoration(shape: BoxShape.circle, color: blue.withOpacity(.08)))),
      Positioned(bottom: -130, right: -100, child: Container(width: 320, height: 320,
        decoration: BoxDecoration(shape: BoxShape.circle, color: gold.withOpacity(.07)))),
      SafeArea(child: Center(child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(24, 30, 24, 28),
        child: ConstrainedBox(constraints: const BoxConstraints(maxWidth: 460),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Center(child: Container(width: 72, height: 72, decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(23),
              gradient: const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight,
                colors: [Color(0xFFFFB66D), Color(0xFFE87932)]),
              boxShadow: [BoxShadow(color: gold.withOpacity(.20), blurRadius: 25, offset: const Offset(0, 9))]),
              child: const Icon(Icons.restaurant_rounded, color: Colors.white, size: 36))),
            const SizedBox(height: 19),
            const Center(child: Text('ELZOCO', style: TextStyle(color: ink, fontSize: 25,
              fontWeight: FontWeight.w900, letterSpacing: 3))),
            const SizedBox(height: 6),
            const Center(child: Text('سامانه یکپارچه منابع انسانی', style: TextStyle(color: muted, fontSize: 12))),
            const SizedBox(height: 42),
            const Text('به الزوکو خوش آمدید', textAlign: TextAlign.right,
              style: TextStyle(color: ink, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('برای ورود، نوع حساب کاربری خود را انتخاب کنید',
              textAlign: TextAlign.right, style: TextStyle(color: muted, fontSize: 12)),
            const SizedBox(height: 24),
            roleCard(context, 'کارمند', 'مشاهده فیش حقوقی و خدمات پرسنلی', 'employee',
              Icons.badge_rounded, const Color(0xFF64B5F6)),
            const SizedBox(height: 14),
            roleCard(context, 'مدیر و سرپرست', 'مدیریت کارکنان و امور سازمانی', 'manager',
              Icons.admin_panel_settings_rounded, const Color(0xFFFFA45B)),
            const SizedBox(height: 30),
            const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              Icon(Icons.fingerprint_rounded, color: gold, size: 19),
              SizedBox(width: 7),
              Text('پس از انتخاب نقش، ورود امن با اثر انگشت', style: TextStyle(color: muted, fontSize: 10)),
            ]),
            const SizedBox(height: 12),
            const Center(child: Text('ELZOCO  •  HUMAN RESOURCE MANAGEMENT',
              style: TextStyle(color: Color(0xFF64748B), fontSize: 8, letterSpacing: 1.2))),
          ]),
        ),
      ))),
    ]),
  );
}

class BiometricGate extends StatelessWidget {
  final Map<String,dynamic> user;
  final bool busy;
  final VoidCallback onAuthenticate, onUsePassword;
  const BiometricGate({super.key, required this.user, required this.busy,
    required this.onAuthenticate, required this.onUsePassword});
  @override Widget build(BuildContext context) => Scaffold(
    backgroundColor: bg,
    body: SafeArea(child: Center(child: SingleChildScrollView(
      padding: const EdgeInsets.all(25),
      child: ConstrainedBox(constraints: const BoxConstraints(maxWidth: 430),
        child: Column(crossAxisAlignment: CrossAxisAlignment.center, children: [
          Align(alignment: Alignment.centerRight, child: TextButton.icon(
            onPressed: onUsePassword, icon: const Icon(Icons.arrow_forward_rounded),
            label: const Text('بازگشت به ورود با رمز'))),
          const SizedBox(height: 18),
          Container(width: 70, height: 70, decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            gradient: const LinearGradient(colors: [Color(0xFFFFB66D), Color(0xFFE87932)])),
            child: const Icon(Icons.restaurant_rounded, color: Colors.white, size: 35)),
          const SizedBox(height: 17),
          const Text('ELZOCO', style: TextStyle(color: ink, fontSize: 21, fontWeight: FontWeight.w900, letterSpacing: 2)),
          const SizedBox(height: 8),
          Text((user['name'] ?? 'کاربر الزوکو').toString(),
            style: const TextStyle(color: ink, fontSize: 18, fontWeight: FontWeight.w800)),
          const SizedBox(height: 5),
          Text(roleText(user['role'] ?? (user['type'] == 'employee' ? 'employee' : 'manager')),
            style: const TextStyle(color: muted, fontSize: 12)),
          const SizedBox(height: 34),
          Container(width: 190, height: 190, decoration: BoxDecoration(shape: BoxShape.circle,
            border: Border.all(color: gold.withOpacity(.35), width: 1.5),
            boxShadow: [BoxShadow(color: gold.withOpacity(.08), blurRadius: 38, spreadRadius: 8)]),
            child: Center(child: Container(width: 148, height: 148,
              decoration: BoxDecoration(shape: BoxShape.circle, color: surface,
                border: Border.all(color: line)),
              child: const Icon(Icons.fingerprint_rounded, size: 92, color: gold)))),
          const SizedBox(height: 28),
          const Text('تأیید هویت برای ورود', style: TextStyle(color: ink, fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          const Text('با اثر انگشت یا روش زیستی ثبت‌شده در دستگاه وارد پنل انتخاب‌شده شوید.',
            textAlign: TextAlign.center, style: TextStyle(color: muted, fontSize: 11, height: 1.8)),
          const SizedBox(height: 25),
          SizedBox(width: double.infinity, height: 54, child: FilledButton.icon(
            onPressed: busy ? null : onAuthenticate,
            icon: busy ? const SizedBox(width: 18, height: 18,
              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
              : const Icon(Icons.fingerprint_rounded, size: 23),
            label: Text(busy ? 'در حال بررسی هویت...' : 'ورود با اثر انگشت',
              style: const TextStyle(fontWeight: FontWeight.w900)))),
          const SizedBox(height: 12),
          TextButton(onPressed: onUsePassword, child: const Text('استفاده از نام کاربری و رمز عبور')),
        ]),
      ),
    ))),
  );
}

class Login extends StatefulWidget {
  final String kind;
  final void Function(String, Map<String,dynamic>) onLogin;
  final VoidCallback onBack;
  const Login({super.key, required this.kind, required this.onLogin, required this.onBack});
  @override State<Login> createState() => _LoginState();
}
class _LoginState extends State<Login> {
  bool busy = false, hide = true;
  String? error;
  final national = TextEditingController(), pc = TextEditingController(),
    phone = TextEditingController(), username = TextEditingController(), password = TextEditingController();
  final LocalAuthentication auth = LocalAuthentication();
  bool get employee => widget.kind == 'employee';
  @override void dispose() { national.dispose(); pc.dispose(); phone.dispose(); username.dispose(); password.dispose(); super.dispose(); }

  Future<void> submit() async {
    setState(() { busy = true; error = null; });
    try {
      final body = employee
        ? {'user_type':'employee','national':national.text.trim(),'personnel':pc.text.trim(),'phone':phone.text.trim()}
        : {'user_type':'admin','username':username.text.trim(),'password':password.text};
      final r = await MobileApi.call('login', method:'POST', body:body);
      final t = r['token'].toString();
      await vault.write(key:'access_token_' + widget.kind, value:t);
      final d = await MobileApi.call('me', token:t);
      var enable = false;
      try {
        if (await auth.isDeviceSupported() && await auth.canCheckBiometrics) {
          enable = await showDialog<bool>(context:context, builder:(ctx) => AlertDialog(
            title: const Text('فعال‌سازی ورود با اثر انگشت'),
            content: const Text('برای دفعات بعد، پس از انتخاب همین نقش، با اثر انگشت وارد شوید؟'),
            actions: [
              TextButton(onPressed:() => Navigator.pop(ctx,false), child: const Text('فعلاً نه')),
              FilledButton(onPressed:() => Navigator.pop(ctx,true), child: const Text('فعال‌سازی')),
            ])) ?? false;
          if (enable) enable = await auth.authenticate(
            localizedReason:'برای فعال‌سازی ورود زیستی، هویت خود را تأیید کنید',
            options: const AuthenticationOptions(biometricOnly:true, stickyAuth:true));
        }
      } catch (_) { enable = false; }
      await vault.write(key:'biometric_enabled_' + widget.kind, value:enable ? 'true' : 'false');
      if (mounted) widget.onLogin(t,d);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally { if (mounted) setState(() => busy = false); }
  }

  Widget field(String label, TextEditingController controller, {TextInputType? type, bool secret = false}) =>
    TextField(controller:controller,
      textDirection:(type != null || secret || !employee) ? TextDirection.ltr : TextDirection.rtl,
      textAlign:(type != null || secret || !employee) ? TextAlign.left : TextAlign.right,
      keyboardType:type, obscureText:secret && hide,
      decoration:InputDecoration(
        labelText:label, prefixIcon:Icon(secret ? Icons.lock_outline_rounded : Icons.person_outline_rounded),
        suffixIcon:secret ? IconButton(onPressed:() => setState(() => hide = !hide),
          icon:Icon(hide ? Icons.visibility_rounded : Icons.visibility_off_rounded)) : null));

  @override Widget build(BuildContext context) => Scaffold(
    backgroundColor:bg,
    appBar:AppBar(backgroundColor:bg, leading:IconButton(onPressed:widget.onBack,
      icon:const Icon(Icons.arrow_forward_rounded, color:ink)), title:Text(employee ? 'ورود کارمند' : 'ورود مدیر',
      style:const TextStyle(color:ink, fontSize:16, fontWeight:FontWeight.w900))),
    body:SafeArea(child:Center(child:SingleChildScrollView(
      padding:const EdgeInsets.fromLTRB(23,18,23,28),
      child:ConstrainedBox(constraints:const BoxConstraints(maxWidth:440),
        child:Column(crossAxisAlignment:CrossAxisAlignment.stretch, children:[
          Center(child:Container(width:62,height:62,decoration:BoxDecoration(
            color:gold.withOpacity(.14),borderRadius:BorderRadius.circular(20)),
            child:Icon(employee ? Icons.badge_rounded : Icons.admin_panel_settings_rounded,color:gold,size:31))),
          const SizedBox(height:18),
          Text(employee ? 'ورود به پنل کارمند' : 'ورود به پنل مدیریت',
            textAlign:TextAlign.center,style:const TextStyle(color:ink,fontSize:21,fontWeight:FontWeight.w900)),
          const SizedBox(height:7),
          Text(employee ? 'اطلاعات حساب پرسنلی خود را وارد کنید' : 'با حساب مدیریتی سازمان وارد شوید',
            textAlign:TextAlign.center,style:const TextStyle(color:muted,fontSize:11)),
          const SizedBox(height:28),
          Container(padding:const EdgeInsets.all(20),decoration:BoxDecoration(
            color:surface,borderRadius:BorderRadius.circular(23),border:Border.all(color:line)),
            child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
              if(employee)...[
                field('کد ملی',national,type:TextInputType.number),
                const SizedBox(height:13),
                field('کد پرسنلی کامل',pc,type:TextInputType.number),
                const SizedBox(height:13),
                field('شماره تلفن همراه',phone,type:TextInputType.phone),
              ] else ...[
                field('نام کاربری',username),
                const SizedBox(height:13),
                field('گذرواژه',password,secret:true),
              ],
              if(error != null) ...[
                const SizedBox(height:13),
                Container(padding:const EdgeInsets.all(11),decoration:BoxDecoration(
                  color:red.withOpacity(.10),borderRadius:BorderRadius.circular(12)),
                  child:Text(error!,style:const TextStyle(color:red,fontSize:11))),
              ],
              const SizedBox(height:20),
              SizedBox(height:53,child:FilledButton(
                onPressed:busy ? null : submit,
                child:busy ? const Row(mainAxisAlignment:MainAxisAlignment.center,mainAxisSize:MainAxisSize.min,children:[SizedBox(width:18,height:18,child:CircularProgressIndicator(strokeWidth:2,color:Colors.white)),SizedBox(width:10),Text('در حال اتصال و بررسی اطلاعات...')])
                  : const Text('ادامه و فعال‌سازی ورود امن',style:TextStyle(fontWeight:FontWeight.w900)))),
            ]),
          ),
          const SizedBox(height:22),
          const Row(mainAxisAlignment:MainAxisAlignment.center,children:[
            Icon(Icons.verified_user_outlined,color:mint,size:16),
            SizedBox(width:7),
            Text('اطلاعات ورود به‌صورت امن در دستگاه نگهداری می‌شود',
              style:TextStyle(color:muted,fontSize:9)),
          ]),
        ]),
      ),
    ))),
  );
}

class Entry{final String id,label,group;final IconData icon;const Entry(this.id,this.label,this.icon,{this.group='اصلی'});}
const adminEntries=[Entry('dashboard','داشبورد',Icons.grid_view_rounded),Entry('upload','بارگذاری اکسل ماهانه',Icons.upload_file_rounded),Entry('employees','کارمندان',Icons.groups_2_rounded),Entry('advances','مساعده و وام',Icons.account_balance_wallet_rounded,group:'personnel'),Entry('leaves','مرخصی‌ها',Icons.event_available_rounded,group:'personnel'),Entry('promotions','ارتقای شغلی',Icons.trending_up_rounded,group:'personnel'),Entry('admins','مدیران',Icons.admin_panel_settings_rounded,group:'system'),Entry('settings','تنظیمات',Icons.tune_rounded,group:'system'),Entry('appearance','ظاهر برنامه',Icons.palette_rounded,group:'system'),Entry('notifications','اعلان‌ها',Icons.notifications_active_rounded)];
const employeeEntries=[Entry('dashboard','خانه',Icons.home_rounded),Entry('requests','درخواست‌های من',Icons.assignment_rounded),Entry('slips','فیش حقوقی',Icons.receipt_long_rounded),Entry('appearance','ظاهر برنامه',Icons.palette_rounded),Entry('notifications','اعلان‌ها',Icons.notifications_active_rounded)];
class Shell extends StatefulWidget{final String token;final Map<String,dynamic> initial;final VoidCallback onLogout;const Shell({super.key,required this.token,required this.initial,required this.onLogout});@override State<Shell> createState()=>_ShellState();}
class _ShellState extends State<Shell>{late Map<String,dynamic> data,user;String page='dashboard',search='',sectionFilter='همه';bool loading=false,uploading=false;List<dynamic> employees=[],leaves=[],finance=[],promos=[],admins=[],alerts=[];Map<String,dynamic> settings={};PlatformFile? file;int year=1405,month=7;bool get employee=>user['type']=='employee';String get role=>(user['role']??'').toString();bool get full=>role=='admin'||role=='senior';List<Entry> get menu=>employee?employeeEntries:adminEntries.where((e)=>!['upload','admins','settings'].contains(e.id)||full).toList();
@override void initState(){super.initState();data=Map.from(widget.initial);user=m(data['user']);PushService.configure(apiBase:apiBase,accessToken:widget.token,onMessage:(message){final title=message.notification?.title??'اعلان جدید دریافت شد';toast(title);if(page=='notifications')loadAlerts();},onOpened:(message){if(mounted){setState(()=>page='notifications');loadAlerts();}});if(!employee)loadAll();}
Future<Map<String,dynamic>> api(String a,{String method='GET',Map<String,dynamic>? body})=>MobileApi.call(a,method:method,token:widget.token,body:body);
Future<void> loadAll()async{setState(()=>loading=true);try{final x=await Future.wait([api('manager_employees'),api('manager_leaves'),api('manager_advances'),api('manager_promotions')]);employees=l(x[0]['employees']);leaves=l(x[1]['requests']);finance=l(x[2]['requests']);promos=l(x[3]['requests']);}catch(e){toast(e.toString(),true);}if(mounted)setState(()=>loading=false);}
Future<void> go(String id)async{Navigator.pop(context);setState(()=>page=id);if(id=='admins')await loadAdmins();if(id=='settings')await loadSettings();if(id=='notifications')await loadAlerts();}
Future<void> refresh()async{try{data=await api('me');user=m(data['user']);if(!employee)await loadAll();else setState((){});}catch(e){toast(e.toString(),true);}}
Future<void> loadAdmins()async{try{admins=l((await api('admin_list'))['admins']);}catch(e){toast(e.toString(),true);}if(mounted)setState((){});}
Future<void> loadSettings()async{try{settings=await api('admin_settings_get');}catch(e){toast(e.toString(),true);}if(mounted)setState((){});}
Future<void> loadAlerts()async{try{final r=await api(employee?'notif_poll':'manager_notif_poll');alerts=l(r['items']);final ids=alerts.map((e)=>m(e)['id']).where((e)=>e!=null).toList();if(ids.isNotEmpty)await api(employee?'notif_ack':'manager_notif_ack',method:'POST',body:{'ids':ids});}catch(e){toast(e.toString(),true);}if(mounted)setState((){});}
void toast(String s,[bool bad=false]){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(s),backgroundColor:bad?red:navy,behavior:SnackBarBehavior.floating));}
Future<void> logout()async{await PushService.unregister(apiBase:apiBase,accessToken:widget.token);widget.onLogout();}
@override Widget build(BuildContext c){String title='داشبورد';for(final e in menu){if(e.id==page)title=e.label;}return Scaffold(drawer:DrawerMenu(user:user,role:role,menu:menu,current:page,onSelect:go,onLogout:logout),appBar:AppBar(title:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(title,style:const TextStyle(fontSize:16,fontWeight:FontWeight.w900,color:ink)),Text(user['name']?.toString()??'همکار الزوکو',style:const TextStyle(fontSize:10,color:muted))]),actions:[IconButton(onPressed:refresh,icon:const Icon(Icons.refresh_rounded)),const SizedBox(width:6)]),body:SafeArea(top:false,child:loading?const Center(child:CircularProgressIndicator()):AnimatedSwitcher(duration:const Duration(milliseconds:300),transitionBuilder:(child,a)=>FadeTransition(opacity:a,child:SlideTransition(position:Tween(begin:const Offset(0,.025),end:Offset.zero).animate(a),child:child)),child:KeyedSubtree(key:ValueKey(page),child:body()))),bottomNavigationBar:SafeArea(top:false,child:Padding(padding:const EdgeInsets.fromLTRB(10,3,10,7),child:ClipRRect(borderRadius:BorderRadius.circular(22),child:NavigationBar(
 selectedIndex:(employee?['dashboard','requests','slips','notifications']:['dashboard','employees','leaves','notifications']).indexOf(page).clamp(0,3).toInt(),
 backgroundColor:surface,elevation:0,indicatorColor:gold.withOpacity(.18),labelBehavior:NavigationDestinationLabelBehavior.alwaysShow,
 onDestinationSelected:(i){final dest=(employee?['dashboard','requests','slips','notifications']:['dashboard','employees','leaves','notifications'])[i];setState(()=>page=dest);if(dest=='notifications')loadAlerts();},
 destinations:employee?const [
  NavigationDestination(icon:Icon3D(Icons.home_outlined,color:cyan,size:28,compact:true),selectedIcon:Icon3D(Icons.home_rounded,color:gold,size:30,compact:true),label:'خانه'),
  NavigationDestination(icon:Icon3D(Icons.assignment_outlined,color:cyan,size:28,compact:true),selectedIcon:Icon3D(Icons.assignment_rounded,color:gold,size:30,compact:true),label:'درخواست‌ها'),
  NavigationDestination(icon:Icon3D(Icons.receipt_long_outlined,color:cyan,size:28,compact:true),selectedIcon:Icon3D(Icons.receipt_long_rounded,color:gold,size:30,compact:true),label:'فیش حقوقی'),
  NavigationDestination(icon:Icon3D(Icons.notifications_none,color:blue,size:28,compact:true),selectedIcon:Icon3D(Icons.notifications_active,color:gold,size:30,compact:true),label:'اعلان‌ها'),
 ]:const [
  NavigationDestination(icon:Icon3D(Icons.grid_view_outlined,color:cyan,size:28,compact:true),selectedIcon:Icon3D(Icons.grid_view_rounded,color:gold,size:30,compact:true),label:'خانه'),
  NavigationDestination(icon:Icon3D(Icons.groups_outlined,color:cyan,size:28,compact:true),selectedIcon:Icon3D(Icons.groups_rounded,color:gold,size:30,compact:true),label:'کارمندان'),
  NavigationDestination(icon:Icon3D(Icons.event_outlined,color:cyan,size:28,compact:true),selectedIcon:Icon3D(Icons.event_available,color:blue,size:30,compact:true),label:'مرخصی'),
  NavigationDestination(icon:Icon(Icons.notifications_none),selectedIcon:Icon(Icons.notifications_active),label:'اعلان‌ها'),
 ])))),
 floatingActionButton:page=='employees'&&full?FloatingActionButton.extended(onPressed:employeeCreate,backgroundColor:blue,icon:const Icon(Icons.person_add,color:Colors.white),label:const Text('افزودن کارمند',style:TextStyle(color:Colors.white))):null);}
Widget body(){switch(page){case 'dashboard':return employee?employeeDashboard():dashboard();case 'employees':return employeesPage();case 'upload':return uploadPage();case 'advances':return requestsPage('finance');case 'leaves':return requestsPage('leave');case 'promotions':return requestsPage('promotion');case 'admins':return adminsPage();case 'settings':return settingsPage();case 'appearance':return appearancePage();case 'notifications':return notificationsPage();case 'requests':return employeeRequests();case 'slips':return slipsPage();default:return dashboard();}}
Widget welcome(){
 return Container(padding:const EdgeInsets.all(20),decoration:BoxDecoration(
  gradient:const LinearGradient(begin:Alignment.topRight,end:Alignment.bottomLeft,colors:[Color(0xFF172B4D),Color(0xFF101B2D),Color(0xFF0B1220)]),
  borderRadius:BorderRadius.circular(25),border:Border.all(color:const Color(0xFF3B82F6).withOpacity(.45)),
  boxShadow:[BoxShadow(color:blue.withOpacity(.12),blurRadius:26,offset:const Offset(0,11))]),
  child:Stack(children:[
   Positioned(left:-16,top:-20,child:Container(width:130,height:130,decoration:BoxDecoration(shape:BoxShape.circle,border:Border.all(color:gold.withOpacity(.10),width:18)))),
   Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
    Row(children:[const Icon(Icons.waving_hand_rounded,color:gold),const SizedBox(width:9),const Text('خوش آمدید',style:TextStyle(color:Color(0xFFD6B69C),fontSize:11)),const Spacer(),const Icon(Icons.restaurant_menu_rounded,color:blue,size:28)]),
    const SizedBox(height:13),Text(user['name']?.toString()??'همکار گرامی',style:const TextStyle(color:Colors.white,fontSize:23,fontWeight:FontWeight.w900)),
    const SizedBox(height:5),Text(employee?'پرتال خدمات کارکنان':roleText(role)+' · '+(full?'دسترسی کامل سامانه':'مدیریت زیرمجموعه'),style:const TextStyle(color:Color(0xFFD1B9A7),fontSize:11)),
    const SizedBox(height:15),Container(padding:const EdgeInsets.symmetric(horizontal:11,vertical:7),decoration:BoxDecoration(color:blue.withOpacity(.16),borderRadius:BorderRadius.circular(20),border:Border.all(color:blue.withOpacity(.35))),child:const Text('ELZOCO  •  HUMAN RESOURCE MANAGEMENT',style:TextStyle(color:Color(0xFFFFC18A),fontSize:8,fontWeight:FontWeight.w900,letterSpacing:.8)))
   ])
  ])
 );
}
Widget section(String a,String b)=>Padding(padding:const EdgeInsets.only(bottom:13),child:Row(crossAxisAlignment:CrossAxisAlignment.center,children:[
 Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
  Text(a,style:const TextStyle(fontSize:16,color:ink,fontWeight:FontWeight.w900)),
  const SizedBox(height:3),Text(b,style:const TextStyle(fontSize:10,color:muted))
 ])),
 Container(width:34,height:3,decoration:BoxDecoration(color:blue,borderRadius:BorderRadius.circular(5)))
]));
Widget dashboard(){
 final c=m(data['counts']),p=m(data['pending']);
 final n=p.values.fold<int>(0,(s,v)=>s+(int.tryParse(v.toString())??0));
 final shortcuts=<Widget>[
  quick('مدیریت کارکنان','فهرست و پرونده پرسنلی',Icons.groups_2_rounded,blue,()=>goDirect('employees')),
  quick('بارگذاری اکسل','ثبت دوره حقوقی جدید',Icons.table_view_rounded,mint,()=>goDirect('upload')),
  quick('مرخصی‌ها','بررسی و تصمیم‌گیری',Icons.calendar_month_rounded,gold,()=>goDirect('leaves')),
  quick('مساعده و وام','درخواست‌های مالی',Icons.account_balance_wallet_rounded,cyan,()=>goDirect('advances')),
  quick('ارتقای شغلی','سوابق و درخواست‌ها',Icons.trending_up_rounded,blue,()=>goDirect('promotions')),
  quick('گزارش‌ها','نمای کلی و آمار',Icons.analytics_rounded,gold,()=>goDirect('employees')),
  quick('اطلاعیه‌ها','پیام‌های سازمانی',Icons.campaign_rounded,cyan,()=>goDirect('notifications')),
  if(full)quick('تنظیمات سامانه','قوانین و دسترسی‌ها',Icons.settings_rounded,muted,()=>goDirect('settings')),
 ];
 return RefreshIndicator(onRefresh:refresh,color:blue,backgroundColor:surface,child:ListView(padding:const EdgeInsets.fromLTRB(16,12,16,28),children:[
  welcome(),const SizedBox(height:21),
  section('نمای کلی سازمان','خلاصه وضعیت کارکنان و گردش درخواست‌ها'),
  GridView.count(shrinkWrap:true,physics:const NeverScrollableScrollPhysics(),crossAxisCount:2,childAspectRatio:1.55,crossAxisSpacing:10,mainAxisSpacing:10,children:[
   metric('کل کارکنان',c['total'],Icons.groups_rounded,blue),
   metric('کارکنان فعال',c['active'],Icons.verified_user_rounded,mint),
   metric('غیرفعال',c['inactive'],Icons.person_off_rounded,red),
   metric('در انتظار بررسی',n,Icons.hourglass_top_rounded,gold)
  ]),
  const SizedBox(height:23),
  Row(children:[Expanded(child:section('دسترسی سریع','میانبرهای اصلی مدیریت سامانه')),Container(padding:const EdgeInsets.symmetric(horizontal:10,vertical:6),decoration:BoxDecoration(color:blue.withOpacity(.13),borderRadius:BorderRadius.circular(20),border:Border.all(color:blue.withOpacity(.3))),child:const Row(children:[Icon(Icons.bolt_rounded,color:gold,size:15),SizedBox(width:4),Text('سریع',style:TextStyle(color:gold,fontSize:9,fontWeight:FontWeight.w900))]))]),
  GridView.count(shrinkWrap:true,physics:const NeverScrollableScrollPhysics(),crossAxisCount:3,childAspectRatio:.91,crossAxisSpacing:9,mainAxisSpacing:10,children:shortcuts),
  const SizedBox(height:12),
  note('محدوده دسترسی',full?'دسترسی شما شامل تمام شعب و کارکنان سامانه است.':'اطلاعات کارکنان و درخواست‌ها فقط در محدوده زیرمجموعه شما نمایش داده می‌شود.',Icons.shield_outlined)
 ]));
}
void goDirect(String id){setState(()=>page=id);if(id=='admins')loadAdmins();if(id=='settings')loadSettings();}
Widget employeeDashboard(){
 return ListView(padding:const EdgeInsets.fromLTRB(16,12,16,28),children:[
  welcome(),const SizedBox(height:21),
  section('دسترسی سریع','خدمات پرکاربرد شما در یک نگاه'),
  GridView.count(shrinkWrap:true,physics:const NeverScrollableScrollPhysics(),crossAxisCount:3,childAspectRatio:.91,crossAxisSpacing:9,mainAxisSpacing:10,children:[
   quick('فیش حقوقی','مشاهده دریافتی',Icons.receipt_long_rounded,blue,()=>goDirect('slips')),
   quick('مرخصی‌ها','ثبت درخواست',Icons.calendar_month_rounded,gold,()=>request('leave')),
   quick('مساعده و وام','درخواست مالی',Icons.account_balance_wallet_rounded,cyan,()=>request('finance')),
   quick('ارتقای شغلی','درخواست ارتقا',Icons.trending_up_rounded,mint,()=>request('promotion')),
   quick('درخواست‌های من','پیگیری وضعیت',Icons.assignment_turned_in_rounded,blue,()=>goDirect('requests')),
   quick('اطلاعیه‌ها','پیام‌های سازمان',Icons.campaign_rounded,gold,()=>goDirect('notifications'))
  ]),
  const SizedBox(height:18),
  section('اطلاعات پرسنلی','مشخصات حساب کاربری'),
  wide('کد پرسنلی',(user['personnel_code']??'—').toString(),Icons.badge_outlined,blue,()=>goDirect('requests')),
  wide('عنوان شغلی',(user['post']??'ثبت نشده').toString(),Icons.work_outline_rounded,cyan,()=>goDirect('requests'))
 ]);
}
Widget metric(String t,dynamic v,IconData i,Color col)=>Container(
 padding:const EdgeInsets.all(14),decoration:BoxDecoration(
 color:surface,borderRadius:BorderRadius.circular(19),
 border:Border.all(color:col.withOpacity(.38)),
 boxShadow:[BoxShadow(color:col.withOpacity(.08),blurRadius:17,offset:const Offset(0,6))]),
 child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
  Row(children:[Icon3D(i,color:col,size:39),const Spacer(),Container(width:6,height:6,decoration:BoxDecoration(color:col,shape:BoxShape.circle))]),
  const Spacer(),ltrValue(fa(v),style:TextStyle(fontSize:23,fontWeight:FontWeight.w900,color:col)),
  Text(t,style:const TextStyle(fontSize:10,color:muted,fontWeight:FontWeight.w700))
 ]));
Widget quick(String t,String s,IconData i,Color col,VoidCallback tap)=>Material(
 color:surface,borderRadius:BorderRadius.circular(18),child:InkWell(onTap:tap,borderRadius:BorderRadius.circular(18),
 child:Container(padding:const EdgeInsets.fromLTRB(9,12,9,11),decoration:BoxDecoration(
 borderRadius:BorderRadius.circular(18),gradient:const LinearGradient(begin:Alignment.topRight,end:Alignment.bottomLeft,colors:[Color(0xFF1F2937),Color(0xFF111827)]),
 border:Border.all(color:col.withOpacity(.38)),boxShadow:[BoxShadow(color:Colors.black.withOpacity(.22),blurRadius:13,offset:const Offset(0,6))]),
 child:Column(mainAxisAlignment:MainAxisAlignment.spaceBetween,children:[
  Icon3D(i,color:col,size:45),
  Column(children:[Text(t,textAlign:TextAlign.center,maxLines:2,overflow:TextOverflow.ellipsis,style:const TextStyle(color:ink,fontWeight:FontWeight.w900,fontSize:10.5)),const SizedBox(height:3),Text(s,textAlign:TextAlign.center,maxLines:2,overflow:TextOverflow.ellipsis,style:const TextStyle(color:muted,fontSize:8))])
 ]))));
Widget wide(String t,String s,IconData i,Color col,VoidCallback tap)=>Container(margin:const EdgeInsets.only(bottom:10),child:Material(
 color:surface,borderRadius:BorderRadius.circular(17),child:InkWell(onTap:tap,borderRadius:BorderRadius.circular(17),
 child:Container(padding:const EdgeInsets.all(13),decoration:BoxDecoration(borderRadius:BorderRadius.circular(17),border:Border.all(color:line),boxShadow:[BoxShadow(color:Colors.black.withOpacity(.18),blurRadius:13,offset:const Offset(0,5))]),
 child:Row(children:[Icon3D(i,color:col,size:46),const SizedBox(width:12),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(t,style:const TextStyle(fontWeight:FontWeight.w900,color:ink,fontSize:12)),Text(s,style:const TextStyle(color:muted,fontSize:10))])),const Icon(Icons.chevron_left_rounded,color:gold)])))));
Widget note(String t,String s,IconData i)=>Container(margin:const EdgeInsets.only(top:12),padding:const EdgeInsets.all(14),decoration:BoxDecoration(color:const Color(0xFF1F2937),borderRadius:BorderRadius.circular(16),border:Border.all(color:blue.withOpacity(.35))),child:Row(children:[Icon(i,color:gold),const SizedBox(width:10),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(t,style:const TextStyle(color:gold,fontWeight:FontWeight.w900,fontSize:11)),Text(s,style:const TextStyle(color:muted,fontSize:10,height:1.6))]))]));
Widget employeesPage(){
 final sections=<String>{'همه',...employees.map((x){final v=(m(x)['section']??'').toString().trim();return v.isEmpty?'بدون سکشن':v;})}.toList();
 final list=employees.where((x){final e=m(x);final q=search.toLowerCase().trim();final matchesQuery=q.isEmpty||['name','personnel_code','post','national_code'].any((k)=>(e[k]??'').toString().toLowerCase().contains(q));final rawSection=(e['section']??'').toString().trim();final matchesSection=sectionFilter=='همه'||(rawSection.isEmpty?'بدون سکشن':rawSection)==sectionFilter;return matchesQuery&&matchesSection;}).toList();
 return Column(children:[
  Padding(padding:const EdgeInsets.fromLTRB(16,10,16,7),child:TextField(onChanged:(v)=>setState(()=>search=v),decoration:InputDecoration(hintText:'جستجوی نام، کد پرسنلی یا سمت',prefixIcon:const Icon(Icons.search_rounded),suffixIcon:search.isNotEmpty?IconButton(onPressed:()=>setState(()=>search=''),icon:const Icon(Icons.close_rounded)):null))),
  if(sections.length>1)SizedBox(height:48,child:ListView.separated(scrollDirection:Axis.horizontal,padding:const EdgeInsets.symmetric(horizontal:16,vertical:5),itemCount:sections.length,separatorBuilder:(_,__)=>const SizedBox(width:7),itemBuilder:(c,i){final s=sections[i];final active=sectionFilter==s;return ChoiceChip(label:Text(s),selected:active,onSelected:(_)=>setState(()=>sectionFilter=s),selectedColor:navy,labelStyle:TextStyle(color:active?Colors.white:muted,fontSize:10,fontWeight:FontWeight.w800),backgroundColor:Colors.white,side:BorderSide(color:active?navy:line),shape:RoundedRectangleBorder(borderRadius:BorderRadius.circular(11)));})),
  Padding(padding:const EdgeInsets.fromLTRB(17,5,17,8),child:Row(children:[Text('${list.length} کارمند',style:const TextStyle(color:ink,fontSize:11,fontWeight:FontWeight.w900)),const Spacer(),Text(sectionFilter=='همه'?'تمام سکشن‌ها':sectionFilter,style:const TextStyle(color:muted,fontSize:10))])),
  Expanded(child:RefreshIndicator(onRefresh:loadAll,child:list.isEmpty?ListView(padding:const EdgeInsets.all(20),children:[empty('کارمندی با این مشخصات پیدا نشد.',Icons.person_search_rounded)]):ListView.builder(padding:const EdgeInsets.fromLTRB(15,3,15,24),itemCount:list.length,itemBuilder:(c,i){final e=m(list[i]);final active=e['inactive']==0||e['inactive']==false;final name=e['name']?.toString()??'؟';final rawSection=(e['section']??'').toString().trim();final section=rawSection.isEmpty?'بدون سکشن':rawSection;return Container(margin:const EdgeInsets.only(bottom:9),padding:const EdgeInsets.all(14),decoration:BoxDecoration(color:surface,borderRadius:BorderRadius.circular(17),border:Border.all(color:line)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
   Row(children:[Container(width:43,height:43,decoration:BoxDecoration(color:navy,borderRadius:BorderRadius.circular(13)),alignment:Alignment.center,child:Text(name.isEmpty?'؟':name.substring(0,1),style:const TextStyle(color:Colors.white,fontSize:16,fontWeight:FontWeight.w900))),const SizedBox(width:11),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(name,style:const TextStyle(fontSize:12,color:ink,fontWeight:FontWeight.w900)),const SizedBox(height:4),Text((e['personnel_code']??'—').toString()+'  ·  '+(e['post']??'بدون سمت').toString(),style:const TextStyle(fontSize:9,color:muted))])),Container(padding:const EdgeInsets.symmetric(horizontal:9,vertical:5),decoration:BoxDecoration(color:active?const Color(0xFFEAF6EF):const Color(0xFFFCEEEE),borderRadius:BorderRadius.circular(8)),child:Text(active?'فعال':'غیرفعال',style:TextStyle(color:active?mint:red,fontSize:9,fontWeight:FontWeight.w900)))]),
   const SizedBox(height:12),Wrap(spacing:7,runSpacing:6,children:[_employeeMeta(Icons.grid_view_rounded,section),if((e['branch']??'').toString().isNotEmpty)_employeeMeta(Icons.storefront_outlined,e['branch'].toString()),if((e['manager_name']??'').toString().isNotEmpty)_employeeMeta(Icons.supervisor_account_outlined,e['manager_name'].toString())]),
   const SizedBox(height:12),Row(children:[Expanded(child:OutlinedButton.icon(onPressed:()=>openEmployeeDossier(e),icon:const Icon(Icons.folder_open_outlined,size:16),label:const Text('پرونده'))),const SizedBox(width:8),Expanded(child:FilledButton.icon(onPressed:()=>openEmployeeDossier(e,openPayslips:true),icon:const Icon(Icons.receipt_long_rounded,size:16),label:const Text('فیش حقوقی')))])
  ]));})))
 ]);
}
Widget _employeeMeta(IconData icon,String label)=>Container(padding:const EdgeInsets.symmetric(horizontal:8,vertical:6),decoration:BoxDecoration(color:bg,borderRadius:BorderRadius.circular(8)),child:Row(mainAxisSize:MainAxisSize.min,children:[Icon(icon,size:12,color:muted),const SizedBox(width:5),Text(label,style:const TextStyle(fontSize:9,color:muted,fontWeight:FontWeight.w700))]));
Future<void> openEmployeeDossier(Map<String,dynamic> row,{bool openPayslips=false})async{try{final id=int.tryParse(row['id'].toString())??0;final r=await api('manager_employee_dossier',method:'POST',body:{'employee_id':id});if(!mounted)return;final dossier=m(r['dossier']);if(openPayslips){Navigator.push(context,MaterialPageRoute(builder:(_)=>EmployeePayslipsScreen(employee:m(dossier['employee']),slips:l(dossier['payslips']),onSlip:(periodId)=>openEmployeeSlip(id,periodId))));return;}Navigator.push(context,MaterialPageRoute(builder:(_)=>EmployeeDossierScreen(dossier:dossier,onSlip:(periodId)=>openEmployeeSlip(id,periodId))));}catch(e){toast(e.toString(),true);}}
Future<void> openEmployeeSlip(int employeeId,int periodId)async{try{final r=await api('manager_employee_payslip',method:'POST',body:{'employee_id':employeeId,'period_id':periodId});if(mounted)Navigator.push(context,MaterialPageRoute(builder:(_)=>Slip(slip:m(r['slip']),user:const {})));}catch(e){toast(e.toString(),true);}}
Widget requestsPage(String kind){final rows=kind=='leave'?leaves:kind=='finance'?finance:promos;final title=kind=='leave'?'درخواست مرخصی':kind=='finance'?'مساعده و وام':'ارتقای شغلی';return RefreshIndicator(onRefresh:loadAll,child:rows.isEmpty?ListView(padding:const EdgeInsets.all(18),children:[section(title,'درخواست‌های قابل مشاهده برای حساب شما'),empty('درخواستی برای بررسی وجود ندارد.',Icons.inbox_outlined)]):ListView(padding:const EdgeInsets.all(15),children:[section(title,rows.length.toString()+' درخواست در محدوده دسترسی شما'),...rows.map((x)=>requestCard(x,kind))]));}
Widget requestCard(dynamic x,String kind){
 final r=m(x),can=r['can_decide']==true||r['can_decide']==1;
 final name=r['name']?.toString()??'کارمند';
 final detail=kind=='leave'?(r['type_label']??'مرخصی').toString()+' · '+(r['from_date']??'').toString():kind=='finance'?(r['kind']=='loan'?'وام':'مساعده')+' · '+money(r['amount']):(r['job_requested']??'ارتقای شغلی').toString();
 final raw=((r['status']??stage(r['stage'])).toString()).toLowerCase();
 final rejected=raw.contains('رد')||raw.contains('reject');
 final approved=raw.contains('تأیید نهایی')||raw.contains('تایید نهایی')||raw.contains('approved')||raw=='final';
 final waiting=!rejected&&!approved;
 final statusColor=rejected?red:approved?mint:gold;
 final statusLabel=rejected?'رد شده':approved?'تأیید نهایی':(r['status']??stage(r['stage'])).toString();
 return Container(margin:const EdgeInsets.only(bottom:12),padding:const EdgeInsets.all(15),decoration:BoxDecoration(
 color:surface,borderRadius:BorderRadius.circular(20),
 border:Border(right:BorderSide(color:statusColor,width:4),top:BorderSide(color:statusColor.withOpacity(.20)),left:BorderSide(color:line),bottom:BorderSide(color:line)),
 boxShadow:[BoxShadow(color:Colors.black.withOpacity(.20),blurRadius:16,offset:const Offset(0,6))]),
 child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
  Row(children:[
   CircleAvatar(backgroundColor:statusColor.withOpacity(.16),child:Text(name.isEmpty?'؟':name.substring(0,1),style:TextStyle(color:statusColor,fontWeight:FontWeight.w900))),
   const SizedBox(width:10),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
    Text(name,style:const TextStyle(fontWeight:FontWeight.w900,color:ink)),
    const SizedBox(height:3),Text((r['tracking']??'').toString()+' · '+(r['personnel_code']??'').toString(),style:const TextStyle(color:muted,fontSize:9))
   ])),
   Container(padding:const EdgeInsets.symmetric(horizontal:10,vertical:7),decoration:BoxDecoration(color:statusColor.withOpacity(.14),borderRadius:BorderRadius.circular(10),border:Border.all(color:statusColor.withOpacity(.4))),child:Row(mainAxisSize:MainAxisSize.min,children:[
    Icon(rejected?Icons.cancel_rounded:approved?Icons.check_circle_rounded:Icons.hourglass_top_rounded,color:statusColor,size:13),const SizedBox(width:5),Text(statusLabel,style:TextStyle(color:statusColor,fontSize:9,fontWeight:FontWeight.w900))
   ]))
  ]),
  const SizedBox(height:13),Container(width:double.infinity,padding:const EdgeInsets.all(11),decoration:BoxDecoration(color:Colors.black.withOpacity(.13),borderRadius:BorderRadius.circular(12)),child:Text(detail,style:const TextStyle(color:ink,fontWeight:FontWeight.w800,fontSize:12))),
  if((r['reason']??'').toString().isNotEmpty)Padding(padding:const EdgeInsets.only(top:9),child:Text(r['reason'].toString(),style:const TextStyle(color:muted,fontSize:10))),
  if(can&&waiting)Padding(padding:const EdgeInsets.only(top:12),child:Row(children:[
   Expanded(child:OutlinedButton(onPressed:()=>decide(kind,r,'reject'),style:OutlinedButton.styleFrom(foregroundColor:red,side:const BorderSide(color:red)),child:const Text('رد درخواست'))),
   const SizedBox(width:9),Expanded(child:FilledButton(onPressed:()=>decide(kind,r,'approve'),style:FilledButton.styleFrom(backgroundColor:mint,foregroundColor:Colors.white),child:const Text('تأیید درخواست')))
  ]))
 ])
);
}
Future<void> decide(String kind,Map<String,dynamic> r,String d)async{final noteCtrl=TextEditingController(),amount=TextEditingController();final ok=await showDialog<bool>(context:context,builder:(c)=>AlertDialog(title:Text(d=='approve'?'تأیید درخواست':'رد درخواست'),content:Column(mainAxisSize:MainAxisSize.min,children:[if(d=='approve'&&kind=='finance')TextField(controller:amount,textDirection:TextDirection.ltr,textAlign:TextAlign.left,keyboardType:TextInputType.number,decoration:const InputDecoration(labelText:'مبلغ تأییدشده (ریال)')),TextField(controller:noteCtrl,maxLines:2,decoration:InputDecoration(labelText:d=='reject'?'دلیل رد':'توضیح'))]),actions:[TextButton(onPressed:()=>Navigator.pop(c,false),child:const Text('انصراف')),FilledButton(onPressed:()=>Navigator.pop(c,true),child:const Text('ثبت'))]));if(ok==true){try{final action=kind=='leave'?'manager_leave_decide':kind=='finance'?'manager_advance_decide':'manager_promo_decide';final b=<String,dynamic>{'id':r['id'],'decision':d,'note':noteCtrl.text};if(amount.text.isNotEmpty)b['approved']=amount.text;final x=await api(action,method:'POST',body:b);toast(x['message']??'ثبت شد.');await loadAll();}catch(e){toast(e.toString(),true);}}noteCtrl.dispose();amount.dispose();}
Widget uploadPage()=>ListView(padding:const EdgeInsets.all(16),children:[section('بارگذاری اکسل ماهانه','ثبت دوره جدید مستقیماً از داخل اپلیکیشن'),Container(padding:const EdgeInsets.all(16),decoration:BoxDecoration(color:surface,borderRadius:BorderRadius.circular(19),border:Border.all(color:line)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[const Text('دوره حقوقی',style:TextStyle(fontWeight:FontWeight.w900)),const SizedBox(height:12),Row(children:[Expanded(child:TextFormField(initialValue:year.toString(),textDirection:TextDirection.ltr,textAlign:TextAlign.left,keyboardType:TextInputType.number,decoration:const InputDecoration(labelText:'سال'),onChanged:(v)=>year=int.tryParse(v)??year)),const SizedBox(width:10),Expanded(child:DropdownButtonFormField<int>(value:month,decoration:const InputDecoration(labelText:'ماه'),items:List.generate(12,(i)=>DropdownMenuItem(value:i+1,child:Text(months[i]))),onChanged:(v)=>setState(()=>month=v??month)))]),const SizedBox(height:16),InkWell(onTap:pickFile,child:Container(width:double.infinity,padding:const EdgeInsets.symmetric(vertical:25),decoration:BoxDecoration(color:const Color(0xFFF5F8FF),borderRadius:BorderRadius.circular(16),border:Border.all(color:blue.withOpacity(.35))),child:Column(children:[Icon(file==null?Icons.cloud_upload_outlined:Icons.description_rounded,size:38,color:blue),const SizedBox(height:8),Text(file?.name??'انتخاب فایل اکسل ماهانه',style:const TextStyle(fontWeight:FontWeight.w900,color:navy)),const Text('فرمت مجاز: XLSX',style:TextStyle(color:muted,fontSize:10))]))),const SizedBox(height:15),SizedBox(width:double.infinity,height:50,child:FilledButton.icon(onPressed:file==null||uploading?null:upload,icon:uploading?const SizedBox(width:18,height:18,child:CircularProgressIndicator(color:Colors.white,strokeWidth:2)):const Icon(Icons.upload_rounded),label:Text(uploading?'در حال پردازش...':'بارگذاری و به‌روزرسانی')))])),note('حفظ سوابق','اطلاعات و سوابق دوره‌های قبلی حذف نمی‌شوند؛ فایل جدید دوره انتخاب‌شده را به‌روزرسانی می‌کند.',Icons.info_outline)]);
Future<void> pickFile()async{final r=await FilePicker.platform.pickFiles(type:FileType.custom,allowedExtensions:['xlsx'],withData:true);if(r!=null&&r.files.isNotEmpty)setState(()=>file=r.files.first);}
Future<void> upload()async{if(file==null)return;setState(()=>uploading=true);try{final r=await MobileApi.upload(widget.token,file!,year,month);toast(r['message']??'بارگذاری موفق بود.');file=null;await refresh();}catch(e){toast(e.toString(),true);}if(mounted)setState(()=>uploading=false);}
Widget adminsPage()=>ListView(padding:const EdgeInsets.all(15),children:[
 Row(children:[Expanded(child:section('مدیران سامانه','حساب‌ها و نقش‌های دسترسی')),FilledButton.icon(onPressed:()=>editAdmin(),icon:const Icon(Icons.add,size:18),label:const Text('حساب جدید'))]),
 if(admins.isEmpty)empty('حسابی ثبت نشده است.',Icons.admin_panel_settings_outlined),
 ...admins.map((x){
  final a=m(x);
  return Card(child:ListTile(
   leading:const CircleAvatar(child:Icon(Icons.shield_outlined)),
   title:Text(a['name']?.toString()??'',style:const TextStyle(fontWeight:FontWeight.w900)),
   subtitle:Text((a['username']??'').toString()+' · '+roleText(a['role']),style:const TextStyle(fontSize:10,color:muted)),
   trailing:PopupMenuButton<String>(
    onSelected:(v){if(v=='edit')editAdmin(a);if(v=='toggle')toggleAdmin(a);},
    itemBuilder:(_)=>const [PopupMenuItem(value:'edit',child:Text('ویرایش')),PopupMenuItem(value:'toggle',child:Text('تغییر وضعیت'))],
   ),
  ));
 })
]);
Future<void> editAdmin([Map<String,dynamic>? a])async{final un=TextEditingController(text:a?['username']?.toString()??''),name=TextEditingController(text:a?['name']?.toString()??''),pass=TextEditingController(),pc=TextEditingController(text:a?['personnel_code']?.toString()??'');String r=a?['role']?.toString()??'manager';final ok=await showModalBottomSheet<bool>(context:context,isScrollControlled:true,builder:(c)=>StatefulBuilder(builder:(c,set)=>Padding(padding:EdgeInsets.fromLTRB(18,18,18,MediaQuery.of(c).viewInsets.bottom+20),child:SingleChildScrollView(child:Column(mainAxisSize:MainAxisSize.min,children:[Text(a==null?'ایجاد حساب مدیریتی':'ویرایش حساب',style:const TextStyle(fontSize:17,fontWeight:FontWeight.w900)),const SizedBox(height:12),TextField(controller:un,textDirection:TextDirection.ltr,textAlign:TextAlign.left,decoration:const InputDecoration(labelText:'نام کاربری')),const SizedBox(height:9),TextField(controller:name,decoration:const InputDecoration(labelText:'نام نمایشی')),const SizedBox(height:9),DropdownButtonFormField<String>(value:r,decoration:const InputDecoration(labelText:'نقش'),items:const [DropdownMenuItem(value:'manager',child:Text('مدیر / سرپرست')),DropdownMenuItem(value:'senior',child:Text('مدیر ارشد')),DropdownMenuItem(value:'admin',child:Text('ادمین'))],onChanged:(v)=>set(()=>r=v??r)),const SizedBox(height:9),TextField(controller:pc,textDirection:TextDirection.ltr,textAlign:TextAlign.left,keyboardType:TextInputType.number,decoration:const InputDecoration(labelText:'کد پرسنلی مرتبط')),const SizedBox(height:9),TextField(controller:pass,textDirection:TextDirection.ltr,textAlign:TextAlign.left,obscureText:true,decoration:InputDecoration(labelText:a==null?'رمز عبور (حداقل ۸ کاراکتر)':'رمز جدید (اختیاری)')),const SizedBox(height:15),SizedBox(width:double.infinity,child:FilledButton(onPressed:()=>Navigator.pop(c,true),child:const Text('ذخیره حساب')))])))));if(ok==true){try{final x=await api('admin_save',method:'POST',body:{'id':a?['id']??0,'username':un.text,'name':name.text,'role':r,'personnel':pc.text,'password':pass.text});toast(x['message']??'ذخیره شد.');await loadAdmins();}catch(e){toast(e.toString(),true);}}un.dispose();name.dispose();pass.dispose();pc.dispose();}
Future<void> toggleAdmin(Map<String,dynamic> a)async{try{final x=await api('admin_toggle',method:'POST',body:{'id':a['id']});toast(x['message']??'وضعیت تغییر کرد.');await loadAdmins();}catch(e){toast(e.toString(),true);}}
Widget appearanceSettings() => Container(
  margin:const EdgeInsets.only(bottom:16),padding:const EdgeInsets.all(17),
  decoration:BoxDecoration(
    gradient:const LinearGradient(begin:Alignment.topRight,end:Alignment.bottomLeft,
      colors:[Color(0xFF172B4D),Color(0xFF101B2D),Color(0xFF0B1220)]),
    borderRadius:BorderRadius.circular(20),border:Border.all(color:blue.withOpacity(.35))),
  child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
    Row(children:[Icon3D(Icons.palette_rounded,color:gold,size:42),const SizedBox(width:12),
      const Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
        Text('پوسته آبی نیمه‌شب',style:TextStyle(color:ink,fontSize:14,fontWeight:FontWeight.w900)),
        SizedBox(height:4),Text('سرمه‌ای عمیق · آبی سازمانی · نارنجی برای تأکید و وضعیت فعال',
          style:TextStyle(color:muted,fontSize:10,height:1.5))
      ]))
    ]),
    const SizedBox(height:15),
    Row(children:[
      for(final color in [navy,surface,blue,gold]) ...[
        Container(width:34,height:28,margin:const EdgeInsets.only(left:8),
          decoration:BoxDecoration(color:color,borderRadius:BorderRadius.circular(8),
            border:Border.all(color:Colors.white.withOpacity(.18))))
      ]
    ]),
    const SizedBox(height:12),
    const Text('حالت تیره یکپارچه برای خوانایی ثابت در تمام صفحه‌ها فعال است.',
      style:TextStyle(color:ink,fontSize:10,fontWeight:FontWeight.w700))
  ]));
Widget appearancePage()=>ListView(padding:const EdgeInsets.all(15),children:[section('ظاهر برنامه','انتخاب پوسته و حالت نمایش برای این دستگاه'),appearanceSettings()]);
Widget settingsPage(){final w=m(settings['windows']),rules=m(settings['rules']);return ListView(padding:const EdgeInsets.all(15),children:[section('تنظیمات سامانه','کنترل زمان‌بندی و سیاست‌های منابع انسانی'),appearanceSettings(),setting('مشاهده فیش حقوقی',windowText(w['payslip']),Icons.receipt_long,()=>windowEdit('payslip',w['payslip'])),setting('ثبت درخواست مساعده',windowText(w['advance']),Icons.payments,()=>windowEdit('advance',w['advance'])),setting('ثبت درخواست وام',windowText(w['loan']),Icons.account_balance_wallet,()=>windowEdit('loan',w['loan'])),note('قوانین مالی','سابقه مساعده: '+(rules['adv_wait_months']??'—').toString()+' ماه · سقف: '+(rules['adv_ratio_pct']??'—').toString()+'٪\\nسابقه وام: '+(rules['loan_wait_months']??'—').toString()+' ماه · سقف: '+(rules['loan_ratio_pct']??'—').toString()+'٪',Icons.rule_folder_outlined),setting('حساب‌های مدیران','مدیریت نقش و دسترسی مدیران',Icons.admin_panel_settings,()=>goDirect('admins'))]);}
String windowText(dynamic x){final a=m(x);return (a['start']??'بدون محدودیت').toString()+' تا '+(a['end']??'بدون محدودیت').toString();}
Widget setting(String t,String s,IconData i,VoidCallback f)=>Card(child:ListTile(onTap:f,leading:Icon(i,color:blue),title:Text(t,style:const TextStyle(fontWeight:FontWeight.w900,fontSize:12)),subtitle:Text(s,style:const TextStyle(fontSize:10,color:muted)),trailing:const Icon(Icons.chevron_left,color:muted)));
Future<void> windowEdit(String which,dynamic value)async{final v=m(value),start=TextEditingController(text:v['start']?.toString()??''),end=TextEditingController(text:v['end']?.toString()??'');final ok=await showDialog<bool>(context:context,builder:(c)=>AlertDialog(title:Text('تنظیم بازه '+which),content:Column(mainAxisSize:MainAxisSize.min,children:[TextField(controller:start,textDirection:TextDirection.ltr,textAlign:TextAlign.left,decoration:const InputDecoration(labelText:'شروع شمسی')),const SizedBox(height:8),TextField(controller:end,textDirection:TextDirection.ltr,textAlign:TextAlign.left,decoration:const InputDecoration(labelText:'پایان شمسی'))]),actions:[TextButton(onPressed:()=>Navigator.pop(c,false),child:const Text('انصراف')),FilledButton(onPressed:()=>Navigator.pop(c,true),child:const Text('ذخیره'))]));if(ok==true){try{await api('admin_window_save',method:'POST',body:{'which':which,'start':start.text,'end':end.text});toast('بازه ذخیره شد.');await loadSettings();}catch(e){toast(e.toString(),true);}}start.dispose();end.dispose();}
Future<void> composeAnnouncement()async{
 final title=TextEditingController(),body=TextEditingController();
 String target='all',kind='announcement';
 final ok=await showDialog<bool>(context:context,builder:(c)=>StatefulBuilder(builder:(c,setD)=>AlertDialog(
  backgroundColor:surface,title:const Text('ارسال اطلاعیه جدید',style:TextStyle(color:ink,fontWeight:FontWeight.w900)),
  content:SingleChildScrollView(child:Column(mainAxisSize:MainAxisSize.min,children:[
   TextField(controller:title,textDirection:TextDirection.rtl,textAlign:TextAlign.right,decoration:const InputDecoration(labelText:'عنوان اطلاعیه')),
   const SizedBox(height:10),TextField(controller:body,textDirection:TextDirection.rtl,textAlign:TextAlign.right,minLines:3,maxLines:6,decoration:const InputDecoration(labelText:'متن اطلاعیه')),
   const SizedBox(height:12),DropdownButtonFormField<String>(value:kind,decoration:const InputDecoration(labelText:'نوع اطلاعیه'),items:const [DropdownMenuItem(value:'announcement',child:Text('اطلاعیه عمومی')),DropdownMenuItem(value:'urgent',child:Text('فوری')),DropdownMenuItem(value:'reminder',child:Text('یادآوری'))],onChanged:(v)=>setD(()=>kind=v??'announcement')),
   const SizedBox(height:10),DropdownButtonFormField<String>(value:target,decoration:const InputDecoration(labelText:'گیرندگان'),items:const [DropdownMenuItem(value:'all',child:Text('تمام کارکنان فعال'))],onChanged:(v)=>setD(()=>target=v??'all')),
   const SizedBox(height:6),const Text('ارسال به گروه‌های انتخابی، شعبه و سکشن در مرحله بعدی اضافه می‌شود.',style:TextStyle(color:muted,fontSize:10))
  ])),
  actions:[TextButton(onPressed:()=>Navigator.pop(c,false),child:const Text('انصراف')),FilledButton(onPressed:()=>Navigator.pop(c,true),child:const Text('ارسال اطلاعیه'))]
 )));
 if(ok==true){
  try{final result=await api('admin_announcement_send',method:'POST',body:{'title':title.text.trim(),'body':body.text.trim(),'target':target,'kind':kind});toast((result['message']??'اطلاعیه ارسال شد.').toString());}
  catch(e){toast(e.toString(),true);}
 }
 title.dispose();body.dispose();
}
Widget notificationsPage()=>RefreshIndicator(onRefresh:loadAlerts,child:ListView(padding:const EdgeInsets.all(15),children:[
 if(full)Padding(padding:const EdgeInsets.only(bottom:14),child:Container(padding:const EdgeInsets.all(17),decoration:BoxDecoration(gradient:const LinearGradient(colors:[Color(0xFF1D4ED8),Color(0xFF172554)]),borderRadius:BorderRadius.circular(20)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[const Icon(Icons.campaign_rounded,color:Colors.white,size:30),const SizedBox(height:10),const Text('مرکز اطلاع‌رسانی',style:TextStyle(color:Colors.white,fontSize:17,fontWeight:FontWeight.w900)),const SizedBox(height:5),const Text('ارسال پیام و اطلاعیه برای کارکنان فعال',style:TextStyle(color:Colors.white70,fontSize:11)),const SizedBox(height:13),SizedBox(width:double.infinity,child:FilledButton.icon(style:FilledButton.styleFrom(backgroundColor:Color(0xFF241F1C),foregroundColor:Color(0xFFF4EAE4)),onPressed:composeAnnouncement,icon:const Icon(Icons.send_rounded),label:const Text('ایجاد و ارسال اطلاعیه')))]))),
 section('اعلان‌های دریافتی','پیام‌ها و رویدادهای مرتبط با حساب شما'),
 if(alerts.isEmpty)empty('اعلان جدیدی وجود ندارد.',Icons.notifications_none),
 ...alerts.map((x){final a=m(x);return Padding(padding:const EdgeInsets.only(bottom:9),child:wide(a['title']?.toString()??'اعلان',a['body']?.toString()??a['message']?.toString()??'',Icons.notifications_active,cyan,(){}));})
]));
Widget employeeRequests()=>ListView(padding:const EdgeInsets.all(15),children:[section('درخواست‌های من','ثبت درخواست و پیگیری وضعیت'),wide('درخواست مرخصی','ثبت درخواست جدید',Icons.event,cyan,()=>request('leave')),wide('وام و مساعده','ثبت درخواست مالی',Icons.payments,mint,()=>request('finance')),wide('ارتقای شغلی','درخواست ارتقا',Icons.trending_up,gold,()=>request('promotion')),...l(m(data['leave'])['requests']).map(history),...l(m(data['advance'])['requests']).map(history),...l(m(data['loan'])['requests']).map(history),...l(m(data['promotion'])['requests']).map(history)]);
Widget history(dynamic x){
 final a=m(x),raw=((a['status']??stage(a['stage'])).toString()).toLowerCase();
 final rejected=raw.contains('رد')||raw.contains('reject');
 final approved=raw.contains('تأیید نهایی')||raw.contains('تایید نهایی')||raw.contains('approved')||raw=='final';
 final col=rejected?red:approved?mint:gold;
 final label=rejected?'رد شده':approved?'تأیید نهایی':(a['status']??stage(a['stage'])).toString();
 return Container(margin:const EdgeInsets.only(bottom:9),padding:const EdgeInsets.all(13),decoration:BoxDecoration(color:surface,borderRadius:BorderRadius.circular(16),border:Border(right:BorderSide(color:col,width:4),top:BorderSide(color:line),left:BorderSide(color:line),bottom:BorderSide(color:line))),child:Row(children:[
  Icon(rejected?Icons.cancel_rounded:approved?Icons.check_circle_rounded:Icons.hourglass_top_rounded,color:col,size:22),const SizedBox(width:10),
  Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
   Text((a['tracking']??'درخواست').toString()+' · '+(a['type_label']??a['kind']??'پرسنلی').toString(),style:const TextStyle(color:ink,fontWeight:FontWeight.w800,fontSize:11)),
   const SizedBox(height:4),Text(label,style:TextStyle(color:col,fontWeight:FontWeight.w900,fontSize:10))
  ]))
 ]));
}
Widget slipsPage(){final ps=l(data['periods']);return ListView(padding:const EdgeInsets.all(15),children:[section('فیش‌های حقوقی','دوره‌های ثبت‌شده'),if(ps.isEmpty)empty('فیشی ثبت نشده است.',Icons.receipt_long),...ps.map((p)=>wide(m(p)['label']?.toString()??'دوره حقوقی',m(p)['has_work']==true?'مشاهده فیش':'کارکرد ثبت نشده',Icons.receipt_long,blue,()=>showSlip(p)))]);}
Future<void> showSlip(dynamic p)async{try{final r=await api('payslip',method:'POST',body:{'period_id':int.tryParse(m(p)['id'].toString())??0});if(mounted)Navigator.push(context,MaterialPageRoute(builder:(_)=>Slip(slip:m(r['slip']),user:user)));}catch(e){toast(e.toString(),true);}}
void request(String k)=>Navigator.push(context,MaterialPageRoute(builder:(_)=>RequestForm(kind:k,data:data,send:(a,b)=>api(a,method:'POST',body:b),refresh:refresh)));
Future<void> employeeCreate()async{
 final fields=<String,TextEditingController>{
  'name':TextEditingController(),'national_code':TextEditingController(),'phone':TextEditingController(),
  'hire_date':TextEditingController(),'personnel_suffix':TextEditingController(),'post':TextEditingController(),
  'branch':TextEditingController(),'section':TextEditingController(),'sheba':TextEditingController(),
  'salary':TextEditingController(),'manager_pc':TextEditingController()
 };
 final labels=<String,String>{
  'name':'نام و نام خانوادگی','national_code':'کد ملی ۱۰ رقمی','phone':'شماره موبایل',
  'hire_date':'تاریخ شروع همکاری شمسی (۱۴۰۵/۰۵/۲۷)','personnel_suffix':'سه رقم انتهایی کد پرسنلی',
  'post':'عنوان شغلی','branch':'شعبه','section':'سکشن / واحد کاری','sheba':'شماره شبا (اختیاری)',
  'salary':'حقوق پایه (ریال)','manager_pc':'کد پرسنلی مدیر مستقیم (اختیاری)'
 };
 final ok=await showModalBottomSheet<bool>(context:context,isScrollControlled:true,backgroundColor:bg,builder:(c)=>Padding(
  padding:EdgeInsets.fromLTRB(18,18,18,MediaQuery.of(c).viewInsets.bottom+20),
  child:SingleChildScrollView(child:Column(mainAxisSize:MainAxisSize.min,crossAxisAlignment:CrossAxisAlignment.stretch,children:[
   const Text('افزودن کارمند',style:TextStyle(fontSize:18,fontWeight:FontWeight.w900,color:ink)),
   const SizedBox(height:5),const Text('کد پرسنلی دقیقاً با قاعده اکسل ساخته می‌شود: جزء N (تاریخ شروع) سپس جزء F (سه رقم انتهایی).',style:TextStyle(color:muted,fontSize:10)),
   const SizedBox(height:14),
   for(final entry in fields.entries) Padding(padding:const EdgeInsets.only(bottom:9),child:entry.key=='manager_pc'?DropdownButtonFormField<String>(value:entry.value.text,decoration:InputDecoration(labelText:labels[entry.key]),items:[const DropdownMenuItem(value:'',child:Text('بدون مدیر مستقیم')),...employees.where((x){final e=m(x);return (e['inactive']==0||e['inactive']==false)&&(e['personnel_code']??'').toString().isNotEmpty;}).map((x){final e=m(x);final pc=e['personnel_code'].toString();return DropdownMenuItem(value:pc,child:Text((e['name']??'کارمند').toString()+' · '+pc,overflow:TextOverflow.ellipsis));})],onChanged:(v)=>entry.value.text=v??'' ):TextField(controller:entry.value,textDirection:['national_code','phone','personnel_suffix','salary'].contains(entry.key)?TextDirection.ltr:TextDirection.rtl,textAlign:['national_code','phone','personnel_suffix','salary'].contains(entry.key)?TextAlign.left:TextAlign.right,keyboardType:['national_code','phone','personnel_suffix','salary'].contains(entry.key)?TextInputType.number:TextInputType.text,decoration:InputDecoration(labelText:labels[entry.key]))),
   const SizedBox(height:8),SizedBox(height:48,child:FilledButton(onPressed:()=>Navigator.pop(c,true),child:const Text('ثبت پرونده کارمند')))
  ])))
 );
 if(ok==true){
  try{
   final body=<String,dynamic>{for(final e in fields.entries)e.key:e.value.text.trim()};
   final result=await api('mobile_employee_create',method:'POST',body:body);
   toast((result['message']??'کارمند ثبت شد.').toString());
   await loadAll();
  }catch(e){toast(e.toString(),true);}
 }
 for(final controller in fields.values){controller.dispose();}
}
Widget empty(String t,IconData i)=>Container(padding:const EdgeInsets.symmetric(vertical:32,horizontal:16),decoration:BoxDecoration(color:Colors.white,borderRadius:BorderRadius.circular(18),border:Border.all(color:line)),child:Column(children:[Icon(i,color:muted,size:34),const SizedBox(height:9),Text(t,style:const TextStyle(color:muted,fontSize:11))]));
}
class EmployeePayslipsScreen extends StatelessWidget{
 final Map<String,dynamic> employee;final List<dynamic> slips;final Future<void> Function(int) onSlip;
 const EmployeePayslipsScreen({super.key,required this.employee,required this.slips,required this.onSlip});
 @override Widget build(BuildContext c)=>Scaffold(
  appBar:AppBar(title:const Text('فیش‌های حقوقی کارمند')),
  body:SafeArea(top:false,child:ListView(padding:const EdgeInsets.fromLTRB(16,16,16,30),children:[
   Container(padding:const EdgeInsets.all(17),decoration:BoxDecoration(gradient:const LinearGradient(colors:[navy,blue]),borderRadius:BorderRadius.circular(19)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
    Text(employee['name']?.toString()??'کارمند',style:const TextStyle(color:Colors.white,fontWeight:FontWeight.w900,fontSize:18)),
    const SizedBox(height:5),Text('کد پرسنلی: ${employee['personnel_code']??'—'}',style:const TextStyle(color:Colors.white70,fontSize:11)),
    const SizedBox(height:9),Text('${slips.length} دوره حقوقی',style:const TextStyle(color:Colors.white70,fontSize:10))
   ])),
   const SizedBox(height:15),
   if(slips.isEmpty)Container(padding:const EdgeInsets.symmetric(vertical:32,horizontal:16),decoration:BoxDecoration(color:surface,borderRadius:BorderRadius.circular(18),border:Border.all(color:line)),child:const Column(children:[Icon(Icons.receipt_long_outlined,color:muted,size:34),SizedBox(height:9),Text('برای این کارمند فیش حقوقی ثبت نشده است.',style:TextStyle(color:muted,fontSize:11))])),
   ...slips.map((x){final p=m(x);return Container(margin:const EdgeInsets.only(bottom:10),decoration:BoxDecoration(color:surface,borderRadius:BorderRadius.circular(17),border:Border.all(color:line)),child:ListTile(
    contentPadding:const EdgeInsets.symmetric(horizontal:15,vertical:7),
    leading:Container(width:43,height:43,decoration:BoxDecoration(color:blue.withOpacity(.09),borderRadius:BorderRadius.circular(13)),child:const Icon(Icons.receipt_long_rounded,color:blue)),
    title:Text(p['label']?.toString()??'دوره حقوقی',style:const TextStyle(fontWeight:FontWeight.w900,fontSize:12)),
    subtitle:Padding(padding:const EdgeInsets.only(top:5),child:Text('ناخالص: ${money(p['gross'])}  ·  کسورات: ${money(p['deductions'])}',style:const TextStyle(fontSize:10,color:muted))),
    trailing:Column(mainAxisAlignment:MainAxisAlignment.center,crossAxisAlignment:CrossAxisAlignment.end,children:[Text(money(p['net']),style:const TextStyle(fontWeight:FontWeight.w900,color:mint,fontSize:12)),const Text('خالص پرداختی',style:TextStyle(fontSize:9,color:muted))]),
    onTap:()=>onSlip(int.tryParse(p['id'].toString())??0)
   ));})
  ]))
 );
}

class EmployeeDossierScreen extends StatelessWidget{
 final Map<String,dynamic> dossier;final Future<void> Function(int) onSlip;
 const EmployeeDossierScreen({super.key,required this.dossier,required this.onSlip});
 Widget group(String title,List<Widget> children)=>Container(margin:const EdgeInsets.only(bottom:12),padding:const EdgeInsets.all(14),decoration:BoxDecoration(color:Colors.white,borderRadius:BorderRadius.circular(17),border:Border.all(color:line)),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[Text(title,style:const TextStyle(fontWeight:FontWeight.w900,color:navy,fontSize:13)),const Divider(),...children]));
 Widget pair(String label,dynamic value)=>Padding(padding:const EdgeInsets.symmetric(vertical:6),child:Row(crossAxisAlignment:CrossAxisAlignment.start,children:[Expanded(child:Text(label,style:const TextStyle(color:muted,fontSize:11))),const SizedBox(width:8),Expanded(child:valueText(value,style:const TextStyle(color:navy,fontSize:11,fontWeight:FontWeight.w700)))]));
 @override Widget build(BuildContext c){final e=m(dossier['employee']),bank=m(dossier['bank']),pay=m(dossier['payroll']),balance=m(dossier['leave_balance']);final slips=l(dossier['payslips']);final fields=l(dossier['profile_fields']);return Scaffold(appBar:AppBar(title:const Text('پرونده پرسنلی')),body:SafeArea(top:false,child:ListView(padding:const EdgeInsets.fromLTRB(15,15,15,28),children:[Container(padding:const EdgeInsets.all(17),decoration:BoxDecoration(gradient:const LinearGradient(colors:[navy,blue]),borderRadius:BorderRadius.circular(19)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(e['name']?.toString()??'کارمند',style:const TextStyle(color:Colors.white,fontWeight:FontWeight.w900,fontSize:19)),Text('کد پرسنلی: ${e['personnel_code']??'—'}',style:const TextStyle(color:Colors.white70,fontSize:11)),Text(e['post']?.toString()??'',style:const TextStyle(color:Colors.white70,fontSize:11))])),const SizedBox(height:12),group('اطلاعات هویتی',[pair('کد ملی',e['national_code']),pair('موبایل',e['phone']),pair('تاریخ شروع همکاری',e['hire_date']),pair('مدیر مستقیم',e['manager_name']),pair('وضعیت',e['inactive']==true?'غیرفعال':'فعال')]),group('اطلاعات بانکی',[pair('شماره شبا',bank['sheba']),pair('نام بانک',bank['bank_name']),pair('شماره حساب',bank['bank_account']),pair('شماره کارت',bank['bank_card'])]),group('حقوق و مزایا',[pair('حقوق فعلی',money(pay['current_salary'])),pair('عنوان شغلی',pay['current_post']),pair('افزایش ناشی از ارتقا',money(pay['promotion_increase_total']))]),if(fields.isNotEmpty)group('اطلاعات تکمیلی',fields.map((x)=>pair(m(x)['label'],m(x)['value'])).toList()),group('مانده مرخصی',[pair('سهمیه سالانه',balance['annual_quota']),pair('استحقاق تا امروز',balance['entitlement']),pair('مصرف‌شده',balance['used']),pair('در انتظار تأیید',balance['pending']),pair('مانده',balance['remaining'])]),group('فیش‌های حقوقی',slips.isEmpty?[const Text('فیشی برای این کارمند ثبت نشده است.',style:TextStyle(color:muted,fontSize:11))]:slips.map((x){final p=m(x);return ListTile(contentPadding:EdgeInsets.zero,title:Text(p['label']?.toString()??'دوره حقوقی',style:const TextStyle(fontWeight:FontWeight.w800)),subtitle:Text('ناخالص: ${money(p['gross'])}  ·  کسورات: ${money(p['deductions'])}'),trailing:Column(mainAxisAlignment:MainAxisAlignment.center,children:[Text(money(p['net']),style:const TextStyle(fontWeight:FontWeight.w900,color:mint)),const Text('خالص پرداختی',style:TextStyle(fontSize:9,color:muted))]),onTap:()=>onSlip(int.tryParse(p['id'].toString())??0));}).toList()),group('سوابق مساعده و وام',l(dossier['advances']).map((x){final r=m(x);return pair((r['kind']=='loan'?'وام':'مساعده')+' · ${r['tracking']??''}',money(r['amount'])+' · ${r['status']??''}');}).toList()),group('سوابق مرخصی',l(dossier['leaves']).map((x){final r=m(x);return pair((r['type_label']??'مرخصی').toString()+' · ${r['tracking']??''}',(r['from_date']??'')+' تا '+(r['to_date']??'')+' · ${r['status']??r['stage']??''}');}).toList())])));}
}

class DrawerMenu extends StatelessWidget {
 final Map<String,dynamic> user;
 final String role,current;
 final List<Entry> menu;
 final Future<void> Function(String) onSelect;
 final Future<void> Function() onLogout;
 const DrawerMenu({super.key,required this.user,required this.role,required this.menu,required this.current,required this.onSelect,required this.onLogout});
 @override Widget build(BuildContext c){
  final groups=<String,List<Entry>>{};
  for(final e in menu){groups.putIfAbsent(e.group,()=>[]).add(e);}
  return Drawer(width:300,backgroundColor:navy,child:SafeArea(child:Column(children:[
   Container(width:double.infinity,padding:const EdgeInsets.all(20),decoration:const BoxDecoration(gradient:LinearGradient(colors:[Color(0xFF172554),Color(0xFF1D4ED8)])),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
    Row(children:[Container(width:46,height:46,decoration:BoxDecoration(color:Colors.white.withOpacity(.13),borderRadius:BorderRadius.circular(15)),child:const Icon(Icons.restaurant_menu,color:gold,size:27)),const SizedBox(width:12),const Text('ELZOCO HRM',style:TextStyle(color:Colors.white,fontWeight:FontWeight.w900,fontSize:16,letterSpacing:1))]),
    const SizedBox(height:20),Text(user['name']?.toString()??'کاربر',style:const TextStyle(color:Colors.white,fontWeight:FontWeight.w900)),Text(roleText(role),style:const TextStyle(color:Colors.white70,fontSize:10))
   ])),
   Expanded(child:ListView(padding:const EdgeInsets.all(12),children:[
    for(final g in groups.entries) Theme(
     data:Theme.of(c).copyWith(dividerColor:Colors.transparent),
     child:ExpansionTile(
      key:PageStorageKey<String>('drawer_group_${g.key}'),
      initiallyExpanded:true,
      tilePadding:const EdgeInsets.symmetric(horizontal:12),
      childrenPadding:const EdgeInsets.only(bottom:7),
      iconColor:gold,collapsedIconColor:muted,
      title:Text(g.key=='personnel'?'امور پرسنلی':g.key=='system'?'مدیریت سامانه':'منوی اصلی',style:const TextStyle(color:Color(0xFF91A8CA),fontSize:10,fontWeight:FontWeight.w900)),
      children:[
       for(final e in g.value) Padding(padding:const EdgeInsets.only(bottom:4),child:Material(color:e.id==current?gold.withOpacity(.14):Colors.transparent,borderRadius:BorderRadius.circular(13),child:InkWell(onTap:()=>onSelect(e.id),borderRadius:BorderRadius.circular(13),child:Padding(padding:const EdgeInsets.symmetric(horizontal:12,vertical:10),child:Row(children:[
        Icon3D(e.icon,color:e.id==current?gold:cyan,size:38,compact:true),
        const SizedBox(width:12),Expanded(child:Text(e.label,style:TextStyle(color:e.id==current?gold:const Color(0xFFD0D9E6),fontWeight:e.id==current?FontWeight.w900:FontWeight.w600,fontSize:12))),
        if(e.id==current)Container(width:5,height:26,decoration:BoxDecoration(color:gold,borderRadius:BorderRadius.circular(8)))
       ])))))
      ],
     ),
    )
   ])),
   const Divider(color:Colors.white12),
   ListTile(onTap:()=>onLogout(),leading:const Icon(Icons.logout,color:Color(0xFFFFA0A0)),title:const Text('خروج از حساب',style:TextStyle(color:Color(0xFFFFB3B3),fontSize:12,fontWeight:FontWeight.w800)))
  ])));
 }
}
Future<String?> pickJalaliDate(BuildContext context,{String? initial})async{
 final p=(initial??'').split('/');int y=int.tryParse(p.isNotEmpty?p[0]:'')??1405;int mo=int.tryParse(p.length>1?p[1]:'')??1;int d=int.tryParse(p.length>2?p[2]:'')??1;
 const names=['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
 return showModalBottomSheet<String>(context:context,builder:(ctx)=>StatefulBuilder(builder:(ctx,set)=>Padding(padding:const EdgeInsets.all(18),child:Column(mainAxisSize:MainAxisSize.min,children:[const Text('انتخاب تاریخ شمسی',style:TextStyle(fontWeight:FontWeight.w900)),const SizedBox(height:12),Row(children:[Expanded(child:DropdownButtonFormField<int>(value:y,decoration:const InputDecoration(labelText:'سال'),items:List.generate(15,(i)=>DropdownMenuItem(value:1398+i,child:Text((1398+i).toString()))),onChanged:(v)=>set(()=>y=v??y))),Expanded(child:DropdownButtonFormField<int>(value:mo,decoration:const InputDecoration(labelText:'ماه'),items:List.generate(12,(i)=>DropdownMenuItem(value:i+1,child:Text(names[i]))),onChanged:(v)=>set(()=>mo=v??mo))),Expanded(child:DropdownButtonFormField<int>(value:d.clamp(1,mo<=6?31:30),decoration:const InputDecoration(labelText:'روز'),items:List.generate(mo<=6?31:30,(i)=>DropdownMenuItem(value:i+1,child:Text((i+1).toString()))),onChanged:(v)=>set(()=>d=v??d)))]),const SizedBox(height:14),FilledButton(onPressed:()=>Navigator.pop(ctx,y.toString()+'/'+mo.toString().padLeft(2,'0')+'/'+d.toString().padLeft(2,'0')),child:const Text('ثبت تاریخ'))]))));
}
class RequestForm extends StatefulWidget{final String kind;final Map<String,dynamic> data;final Future<Map<String,dynamic>> Function(String,Map<String,dynamic>) send;final Future<void> Function() refresh;const RequestForm({super.key,required this.kind,required this.data,required this.send,required this.refresh});@override State<RequestForm> createState()=>_RequestFormState();}
class _RequestFormState extends State<RequestForm>{final amount=TextEditingController(),sheba=TextEditingController(),from=TextEditingController(),to=TextEditingController(),st=TextEditingController(),et=TextEditingController(),reason=TextEditingController();String type='daily',fin='advance';bool busy=false;@override void dispose(){for(final c in [amount,sheba,from,to,st,et,reason]){c.dispose();}super.dispose();}
Widget dateField(String label,TextEditingController c)=>InkWell(onTap:()async{final v=await pickJalaliDate(context,initial:c.text);if(v!=null)setState(()=>c.text=v);},child:InputDecorator(decoration:InputDecoration(labelText:label,prefixIcon:const Icon(Icons.calendar_month),suffixIcon:const Icon(Icons.arrow_drop_down)),child:Directionality(textDirection:TextDirection.ltr,child:Text(c.text.isEmpty?'انتخاب از تقویم':c.text,textAlign:TextAlign.left))));
Widget field(String label,TextEditingController c,{int lines=1,TextInputType? keyboard})=>TextField(controller:c,textDirection:keyboard!=null?TextDirection.ltr:TextDirection.rtl,textAlign:keyboard!=null?TextAlign.left:TextAlign.right,maxLines:lines,keyboardType:keyboard,decoration:InputDecoration(labelText:label));
Future<void> submit()async{setState(()=>busy=true);try{late String action;late Map<String,dynamic> body;if(widget.kind=='leave'){action='leave_submit';body={'type':type,'from':from.text,'to':type=='hourly'?from.text:to.text,'start_time':st.text,'end_time':et.text,'reason':reason.text};}else if(widget.kind=='finance'){action=fin=='loan'?'loan_submit':'advance_submit';body={'amount':amount.text,'sheba':sheba.text,'use_registered':sheba.text.isEmpty};}else{action='promo_submit';body={'job':amount.text,'reason':reason.text};}final r=await widget.send(action,body);if(mounted){ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(r['message']??'درخواست ثبت شد.')));await widget.refresh();Navigator.pop(context);}}catch(e){if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(e.toString())));}if(mounted)setState(()=>busy=false);}
@override Widget build(BuildContext context){final types=l(m(widget.data['leave'])['types']);final balance=m(m(widget.data['leave'])['balance']);final overview=m(widget.data[fin]);final registered=(overview['registered_sheba']??'').toString();return Scaffold(appBar:AppBar(title:Text(widget.kind=='leave'?'درخواست مرخصی':widget.kind=='finance'?'وام و مساعده':'ارتقای شغلی')),body:SafeArea(top:false,child:ListView(padding:const EdgeInsets.fromLTRB(16,16,16,28),children:[if(widget.kind=='leave')...[
Container(padding:const EdgeInsets.all(15),decoration:BoxDecoration(color:surface,borderRadius:BorderRadius.circular(17),border:Border.all(color:line)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[const Text('مانده مرخصی استحقاقی',style:TextStyle(fontWeight:FontWeight.w900)),Text('${fa(balance['remaining']??0)} روز',style:const TextStyle(fontSize:24,color:blue,fontWeight:FontWeight.w900)),Text('سهمیه ۲۶ روز · مصرف‌شده ${fa(balance['used']??0)} · در انتظار ${fa(balance['pending']??0)}',style:const TextStyle(fontSize:10,color:muted))])),const SizedBox(height:12),DropdownButtonFormField<String>(value:types.isEmpty?type:types.firstWhere((x)=>m(x)['key']==type,orElse:()=>types.first)['key'].toString(),decoration:const InputDecoration(labelText:'نوع مرخصی'),items:types.map((x)=>DropdownMenuItem(value:m(x)['key'].toString(),child:Text(m(x)['label'].toString()))).toList(),onChanged:(v)=>setState(()=>type=v??type)),const SizedBox(height:10),dateField('تاریخ شروع',from),if(type!='hourly')... [const SizedBox(height:10),dateField('تاریخ پایان',to)]else... [const SizedBox(height:10),field('ساعت شروع',st),const SizedBox(height:10),field('ساعت پایان',et)],const SizedBox(height:10),field('دلیل درخواست',reason,lines:3)]else if(widget.kind=='finance')...[
Container(padding:const EdgeInsets.all(16),decoration:BoxDecoration(gradient:const LinearGradient(colors:[navy,blue]),borderRadius:BorderRadius.circular(18)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(fin=='loan'?'سقف مجاز وام':'سقف مجاز مساعده',style:const TextStyle(color:Colors.white70)),Text(money(overview['max']),style:const TextStyle(color:Colors.white,fontSize:25,fontWeight:FontWeight.w900)),Text('حقوق مبنا: ${money(overview['salary'])}',style:const TextStyle(color:Colors.white70,fontSize:10)),if((overview['reason']??'').toString().isNotEmpty)Text(overview['reason'].toString(),style:const TextStyle(color:Colors.white70,fontSize:10))])),const SizedBox(height:12),DropdownButtonFormField<String>(value:fin,decoration:const InputDecoration(labelText:'نوع درخواست'),items:const [DropdownMenuItem(value:'advance',child:Text('مساعده')),DropdownMenuItem(value:'loan',child:Text('وام'))],onChanged:(v)=>setState(()=>fin=v??fin)),const SizedBox(height:10),field('مبلغ درخواستی (ریال)',amount,keyboard:TextInputType.number),const SizedBox(height:10),if(registered.isNotEmpty)Text('شبای ثبت‌شده در پرونده: IR${registered}',style:const TextStyle(fontSize:11,color:muted))else field('شماره شبا (۲۴ رقم)',sheba,keyboard:TextInputType.number)]else... [field('عنوان شغلی درخواستی',amount),const SizedBox(height:10),field('دلیل درخواست',reason,lines:3)],const SizedBox(height:18),FilledButton(onPressed:busy||(widget.kind=='finance'&&overview['can_request']==false)?null:submit,child:busy?const CircularProgressIndicator(color:Colors.white):Text(widget.kind=='finance'&&overview['can_request']==false?'امکان ثبت درخواست وجود ندارد':'ارسال درخواست'))])));}}
class Slip extends StatelessWidget{
 final Map<String,dynamic> slip,user;const Slip({super.key,required this.slip,required this.user});
 Widget info(String label,dynamic value)=>Padding(padding:const EdgeInsets.symmetric(vertical:6),child:Row(children:[Expanded(child:Text(label,style:const TextStyle(color:Colors.white70,fontSize:11))),Flexible(child:Text(value?.toString()??'—',textAlign:TextAlign.left,style:const TextStyle(color:Colors.white,fontSize:11,fontWeight:FontWeight.w800)))]));
 Widget total(String label,dynamic value,Color color)=>Container(padding:const EdgeInsets.symmetric(horizontal:15,vertical:14),decoration:BoxDecoration(color:color.withOpacity(.08),borderRadius:BorderRadius.circular(15),border:Border.all(color:color.withOpacity(.16))),child:Row(children:[Expanded(child:Text(label,style:TextStyle(color:color,fontWeight:FontWeight.w900,fontSize:12))),Text(money(value),style:TextStyle(color:color,fontWeight:FontWeight.w900,fontSize:13))]));
 Widget items(String title,List<dynamic> rows,IconData icon,Color color)=>Container(padding:const EdgeInsets.all(15),decoration:BoxDecoration(color:surface,borderRadius:BorderRadius.circular(20),border:Border.all(color:line),boxShadow:[BoxShadow(color:navy.withOpacity(.045),blurRadius:16,offset:const Offset(0,6))]),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[Row(children:[Icon3D(icon,color:color,size:36,compact:true),const SizedBox(width:10),Text(title,style:const TextStyle(fontWeight:FontWeight.w900,color:navy,fontSize:13))]),const Divider(height:22),if(rows.isEmpty)const Text('موردی ثبت نشده است.',style:TextStyle(color:muted,fontSize:11)),...rows.map((x){final r=m(x);return Padding(padding:const EdgeInsets.symmetric(vertical:8),child:Row(children:[Expanded(child:Text(r['label']?.toString()??'',style:const TextStyle(fontSize:11,color:ink,fontWeight:FontWeight.w600))),const SizedBox(width:8),Text(money(r['amount']),style:const TextStyle(fontSize:11,fontWeight:FontWeight.w900,color:ink))]));})]));
 @override Widget build(BuildContext c){
  final h=m(slip['header']),t=m(slip['totals']);final net=t['net']??slip['net']??0;
  if(slip['has_work']!=true)return Scaffold(appBar:AppBar(title:const Text('فیش حقوقی')),body:SafeArea(child:Center(child:Text(slip['message']?.toString()??'کارکرد ثبت نشده است.'))));
  return Scaffold(appBar:AppBar(title:const Text('فیش حقوقی')),body:SafeArea(top:false,child:ListView(padding:const EdgeInsets.fromLTRB(15,14,15,32),children:[
   Container(padding:const EdgeInsets.all(18),decoration:BoxDecoration(gradient:const LinearGradient(begin:Alignment.topRight,end:Alignment.bottomLeft,colors:[Color(0xFF111827),Color(0xFF1D4ED8),Color(0xFF1E40AF)]),borderRadius:BorderRadius.circular(23),boxShadow:[BoxShadow(color:navy.withOpacity(.18),blurRadius:22,offset:const Offset(0,9))]),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[Row(children:[const Icon(Icons.receipt_long_rounded,color:gold,size:25),const SizedBox(width:9),const Text('فیش حقوقی ELZOCO',style:TextStyle(color:Colors.white,fontWeight:FontWeight.w900,fontSize:17)),const Spacer(),const Icon(Icons.verified_rounded,color:Colors.white70,size:18)]),const SizedBox(height:5),Text(m(slip['period'])['label']?.toString()??'',style:const TextStyle(color:Colors.white70,fontSize:11)),const Divider(color:Colors.white24,height:22),info('نام و نام خانوادگی',h['name']??user['name']),info('کد پرسنلی',h['personnel']??user['personnel_code']),info('عنوان شغلی',h['post']??user['post']),info('روزهای کارکرد',h['days'])])),
   const SizedBox(height:13),Container(padding:const EdgeInsets.all(19),decoration:BoxDecoration(color:navy,borderRadius:BorderRadius.circular(22),boxShadow:[BoxShadow(color:navy.withOpacity(.16),blurRadius:20,offset:const Offset(0,8))]),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[const Text('مبلغ نهایی قابل پرداخت',style:TextStyle(color:Colors.white70,fontSize:12,fontWeight:FontWeight.w700)),const SizedBox(height:8),Text(money(net),style:const TextStyle(color:gold,fontSize:25,fontWeight:FontWeight.w900)),const SizedBox(height:6),Text('خالص پرداختی پس از کسر تمام کسورات',style:TextStyle(color:Colors.white.withOpacity(.72),fontSize:10))])),
   const SizedBox(height:14),items('ریز دریافتی‌ها',l(slip['pay']),Icons.south_west_rounded,cyan),const SizedBox(height:9),total('جمع کل دریافتی‌ها',t['pay']??0,cyan),
   const SizedBox(height:14),items('ریز کسورات',l(slip['ded_details']).isNotEmpty?l(slip['ded_details']):l(slip['ded']),Icons.north_east_rounded,red),const SizedBox(height:9),total('جمع کل کسورات',t['ded']??0,red),
   const SizedBox(height:13),Container(padding:const EdgeInsets.all(14),decoration:BoxDecoration(color:surface,borderRadius:BorderRadius.circular(15),border:Border.all(color:line)),child:const Row(children:[Icon(Icons.info_outline_rounded,color:blue,size:18),SizedBox(width:8),Expanded(child:Text('مبالغ این فیش بر اساس اطلاعات ثبت‌شده برای همین دوره نمایش داده می‌شود.',style:TextStyle(color:ink,fontSize:10,height:1.5)))]))
  ])));
 }
}