import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:uuid/uuid.dart';

import '../auth/secure_session_store.dart';

class DeviceIdentity {
  DeviceIdentity(this._store);

  final SecureSessionStore _store;
  final DeviceInfoPlugin _deviceInfo = DeviceInfoPlugin();
  final Uuid _uuid = const Uuid();

  Future<DeviceMetadata> load() async {
    String? installationId = await _store.readInstallationId();
    if (installationId == null || installationId.isEmpty) {
      installationId = _uuid.v4();
      await _store.writeInstallationId(installationId);
    }

    final PackageInfo packageInfo = await PackageInfo.fromPlatform();

    if (Platform.isAndroid) {
      final AndroidDeviceInfo info = await _deviceInfo.androidInfo;
      return DeviceMetadata(
        installationId: installationId,
        platform: 'ANDROID',
        deviceName: '${info.manufacturer} ${info.model}'.trim(),
        deviceModel: info.model,
        osVersion: info.version.release,
        appVersion: '${packageInfo.version}+${packageInfo.buildNumber}',
      );
    }

    if (Platform.isIOS) {
      final IosDeviceInfo info = await _deviceInfo.iosInfo;
      return DeviceMetadata(
        installationId: installationId,
        platform: 'IOS',
        deviceName: info.name,
        deviceModel: info.utsname.machine,
        osVersion: info.systemVersion,
        appVersion: '${packageInfo.version}+${packageInfo.buildNumber}',
      );
    }

    throw UnsupportedError('TradeX mobile supports Android and iOS only.');
  }
}

class DeviceMetadata {
  const DeviceMetadata({
    required this.installationId,
    required this.platform,
    required this.deviceName,
    required this.deviceModel,
    required this.osVersion,
    required this.appVersion,
  });

  final String installationId;
  final String platform;
  final String deviceName;
  final String deviceModel;
  final String osVersion;
  final String appVersion;

  Map<String, dynamic> toApiJson() => <String, dynamic>{
        'installation_id': installationId,
        'platform': platform,
        'device_name': deviceName,
        'device_model': deviceModel,
        'os_version': osVersion,
        'app_version': appVersion,
      };
}
