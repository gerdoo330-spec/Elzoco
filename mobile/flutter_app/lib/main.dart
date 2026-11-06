import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:http/http.dart' as http;

const apiBase = String.fromEnvironment('API_BASE_URL', defaultValue: 'https://YOUR-HOST/payroll');
const secureStore = FlutterSecureStorage();

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(statusBarColor: Color(0xFF102B49), statusBarIconBrightness: Brightness.light));
  runApp(const ElzocoApp());
}

class ElzocoApp extends StatelessWidget {
  const ElzocoApp({super.key});
  @override Widget build(BuildContext context) => MaterialApp(
    debugShowCheckedModeBanner: false,
    title: 'Elzoco HRM',
    theme: ThemeData(
      useMaterial3: true,
      scaffoldBackgroundColor: const Color(0xFFF4F7FB),
      colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF0AA992)),
      textTheme: GoogleFonts.vazirmatnTextTheme(),
      inputDecorationTheme: InputDecorationTheme(
        filled: true, fillColor: Colors.white,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFFE2EAF2))),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFFE2EAF2))),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Color(0xFF0AA992), width: 1.5)),
      ),
    ),
    home: const Directionality(textDirection: TextDirection.rtl, child: LoginPage()),
  );
}

class MobileApi {
  static Future<Map<String,dynamic>> post(String action, Map<String,dynamic> data) async {
    final response = await http.post(
      Uri.parse('$apiBase/api/mobile.php?action=$action'),
      headers: {'Content-Type':'application/json','Accept':'application/json'},
      body: jsonEncode(data),
    ).timeout(const Duration(seconds: 25));
    final value = jsonDecode(response.body);
    if (value is! Map<String,dynamic>) throw Exception('پاسخ سرور معتبر نیست.');
    return value;
  }
}

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});
  @override State<LoginPage> createState() => _LoginPageState();
}
class _LoginPageState extends State<LoginPage> {
  String type = 'employee';
  bool busy = false, hidePassword = true;
  String? error;
  final national = TextEditingController(), personnel = TextEditingController(), phone = TextEditingController();
  final username = TextEditingController(), password = TextEditingController();

  @override void dispose() {
    national.dispose(); personnel.dispose(); phone.dispose(); username.dispose(); password.dispose(); super.dispose();
  }

  Future<void> signIn() async {
    setState(() { busy = true; error = null; });
    try {
      final payload = type == 'employee'
        ? {'user_type':'employee','national':national.text.trim(),'personnel':personnel.text.trim(),'phone':phone.text.trim()}
        : {'user_type':'admin','username':username.text.trim(),'password':password.text};
      final result = await MobileApi.post('login', payload);
      if (result['ok'] != true) throw Exception(result['message'] ?? 'ورود انجام نشد.');
      await secureStore.write(key:'access_token', value:result['token'] as String);
      await secureStore.write(key:'user_type', value:type);
      if (!mounted) return;
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => HomeShell(user: Map<String,dynamic>.from(result['user'] as Map))));
    } catch (e) {
      if (mounted) setState(() => error = e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override Widget build(BuildContext context) => Scaffold(
    body: Stack(children: [
      Positioned(top:-90,left:-80,child:Container(width:250,height:250,decoration:const BoxDecoration(shape:BoxShape.circle,color:Color(0x1510A994)))),
      SafeArea(child:Center(child:SingleChildScrollView(
        padding:const EdgeInsets.all(22),
        child:ConstrainedBox(constraints:const BoxConstraints(maxWidth:460),child:Column(crossAxisAlignment:CrossAxisAlignment.stretch,children:[
          const SizedBox(height:25),
          Center(child:Container(width:68,height:68,decoration:BoxDecoration(
            gradient:const LinearGradient(colors:[Color(0xFF173B5C),Color(0xFF0A8F88)]),
            borderRadius:BorderRadius.circular(21),
            boxShadow:[BoxShadow(color:const Color(0xFF173B5C).withOpacity(.2),blurRadius:20,offset:const Offset(0,9))],
          ),child:const Icon(Icons.account_balance_wallet_rounded,color:Colors.white,size:34))),
          const SizedBox(height:22),
          const Text('Elzoco HRM',textAlign:TextAlign.center,style:TextStyle(fontSize:27,fontWeight:FontWeight.w900,color:Color(0xFF173B5C))),
          const SizedBox(height:5),
          const Text('سامانه یکپارچه منابع انسانی',textAlign:TextAlign.center,style:TextStyle(color:Color(0xFF8292A4))),
          const SizedBox(height:28),
          Container(padding:const EdgeInsets.all(5),decoration:BoxDecoration(color:const Color(0xFFE9EFF5),borderRadius:BorderRadius.circular(15)),child:Row(children:[
            _typeButton('employee','کارمند',Icons.person_outline),
            _typeButton('admin','مدیر و سرپرست',Icons.admin_panel_settings_outlined),
          ])),
          const SizedBox(height:20),
          if(type=='employee') ...[
            _field('کد ملی',national,Icons.badge_outlined,keyboard:TextInputType.number),
            const SizedBox(height:11),
            _field('کد پرسنلی کامل',personnel,Icons.confirmation_number_outlined,keyboard:TextInputType.number),
            const SizedBox(height:11),
            _field('شماره موبایل',phone,Icons.phone_android,keyboard:TextInputType.phone),
          ] else ...[
            _field('نام کاربری',username,Icons.person_outline),
            const SizedBox(height:11),
            TextField(controller:password,obscureText:hidePassword,decoration:InputDecoration(
              labelText:'رمز عبور',prefixIcon:const Icon(Icons.lock_outline),
              suffixIcon:IconButton(onPressed:()=>setState(()=>hidePassword=!hidePassword),icon:Icon(hidePassword?Icons.visibility_outlined:Icons.visibility_off_outlined)),
            )),
          ],
          if(error!=null) ...[
            const SizedBox(height:12),
            Container(padding:const EdgeInsets.all(12),decoration:BoxDecoration(color:const Color(0xFFFFF0ED),borderRadius:BorderRadius.circular(12)),child:Text(error!,style:const TextStyle(color:Color(0xFFB84E3F),fontSize:12))),
          ],
          const SizedBox(height:18),
          SizedBox(height:52,child:FilledButton(
            onPressed:busy?null:signIn,
            style:FilledButton.styleFrom(backgroundColor:const Color(0xFF0AA992),shape:RoundedRectangleBorder(borderRadius:BorderRadius.circular(14))),
            child:busy?const SizedBox(width:22,height:22,child:CircularProgressIndicator(strokeWidth:2,color:Colors.white)):const Text('ورود به سامانه',style:TextStyle(fontWeight:FontWeight.bold)),
          )),
          const SizedBox(height:24),
          const Text('ورود امن و یکپارچه با سامانه منابع انسانی الزوکو',textAlign:TextAlign.center,style:TextStyle(color:Color(0xFF9AA7B5),fontSize:11)),
        ])),
      ))),
    ]),
  );

  Widget _typeButton(String value,String label,IconData icon) => Expanded(child:GestureDetector(
    onTap:()=>setState(()=>type=value),
    child:AnimatedContainer(duration:const Duration(milliseconds:180),padding:const EdgeInsets.symmetric(vertical:12),
      decoration:BoxDecoration(color:type==value?Colors.white:Colors.transparent,borderRadius:BorderRadius.circular(11),boxShadow:type==value?[const BoxShadow(color:Color(0x120F2F4D),blurRadius:9,offset:Offset(0,3))]:[]),
      child:Row(mainAxisAlignment:MainAxisAlignment.center,children:[
        Icon(icon,size:17,color:type==value?const Color(0xFF0A9F91):const Color(0xFF8292A4)),
        const SizedBox(width:6),
        Flexible(child:Text(label,style:TextStyle(fontSize:12,fontWeight:FontWeight.w700,color:type==value?const Color(0xFF173B5C):const Color(0xFF8292A4)))),
      ]),
    ),
  ));
  Widget _field(String label,TextEditingController controller,IconData icon,{TextInputType? keyboard}) => TextField(
    controller:controller,keyboardType:keyboard,decoration:InputDecoration(labelText:label,prefixIcon:Icon(icon)),
  );
}

class HomeShell extends StatelessWidget {
  final Map<String,dynamic> user;
  const HomeShell({super.key,required this.user});
  @override Widget build(BuildContext context) {
    final role=user['role']?.toString();
    final title=user['type']=='employee'?'پنل کارمند':role=='manager'?'پنل سرپرست':role=='senior'?'پنل مدیر ارشد':'پنل مدیریت';
    return Scaffold(
      appBar:AppBar(
        backgroundColor:const Color(0xFF173B5C),foregroundColor:Colors.white,title:Text(title),
        actions:[IconButton(onPressed:()async{
          final token=await secureStore.read(key:'access_token');
          if(token!=null){try{await http.post(Uri.parse('$apiBase/api/mobile.php?action=logout'),headers:{'Authorization':'Bearer $token'});}catch(_){}}
          await secureStore.deleteAll();
          if(context.mounted)Navigator.pushAndRemoveUntil(context,MaterialPageRoute(builder:(_)=>const LoginPage()),(_)=>false);
        },icon:const Icon(Icons.logout_rounded))],
      ),
      body:Padding(padding:const EdgeInsets.all(22),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
        Text('سلام، '+(user['name']??'کاربر').toString(),style:const TextStyle(fontSize:23,fontWeight:FontWeight.w900,color:Color(0xFF173B5C))),
        const SizedBox(height:8),
        const Text('به اپلیکیشن منابع انسانی الزوکو خوش آمدید.',style:TextStyle(color:Color(0xFF8292A4))),
        const SizedBox(height:22),
        Container(width:double.infinity,padding:const EdgeInsets.all(18),decoration:BoxDecoration(gradient:const LinearGradient(colors:[Color(0xFF173B5C),Color(0xFF0B827E)]),borderRadius:BorderRadius.circular(18)),child:const Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
          Text('Elzoco HRM',style:TextStyle(color:Colors.white,fontSize:20,fontWeight:FontWeight.w900)),
          SizedBox(height:5),Text('اتصال امن به سامانه برقرار شد',style:TextStyle(color:Color(0xFFD1E8E7),fontSize:12)),
        ])),
        const SizedBox(height:18),
        const Text('ماژول‌های مرحله بعد',style:TextStyle(fontWeight:FontWeight.w800,color:Color(0xFF173B5C))),
        const SizedBox(height:10),
        Wrap(spacing:9,runSpacing:9,children:(user['type']=='employee'?['فیش حقوقی','درخواست مرخصی','وام و مساعده','ارتقای گروه شغلی','اعلان‌ها']:['داشبورد مدیریتی','کارکنان','درخواست‌های در انتظار','گزارش‌ها']).map((x)=>Chip(avatar:const Icon(Icons.grid_view_rounded,size:17),label:Text(x),backgroundColor:Colors.white,side:BorderSide.none,padding:const EdgeInsets.all(8))).toList()),
      ])),
    );
  }
}
