# 🚨 Emergency Trigger App

A comprehensive Android emergency response app with **3 unique triggers** and **multiple alert actions**.

---

## ✅ Triggers

| # | Trigger | Detection Method | Sensitivity |
|---|---------|------------------|-------------|
| 🎤 | **Voice** | "hello hello hello" | 3 speech patterns in quick succession |
| 🔘 | **Power Button** | Screen on/off detection | 4 presses in 3 seconds |
| 📡 | **ESP32 Bluetooth** | BLE button notification | External hardware trigger |

---

## 🚨 Emergency Actions

When a trigger fires, the app can:

| Action | Description |
|--------|-------------|
| 📳 **Vibrate** | SOS vibration pattern (... --- ...) |
| 🔔 **Notification** | High-priority alert notification |
| 📱 **SMS Alert** | Send emergency SMS to multiple contacts with location |
| 📞 **Auto-Call** | Automatically call configured emergency number |
| 🌐 **Webhook** | POST JSON to your server |

---

## 📁 Project Structure

```
c:\xampp\htdocs\App\
├── EmergencyTrigger/                    # Android App
│   ├── app/
│   │   ├── src/main/
│   │   │   ├── kotlin/com/emergency/trigger/
│   │   │   │   ├── MainActivity.kt           # Main UI with all settings
│   │   │   │   ├── service/
│   │   │   │   │   └── TriggerService.kt     # Background foreground service
│   │   │   │   ├── trigger/
│   │   │   │   │   ├── VoiceTrigger.kt       # Simple audio energy detection
│   │   │   │   │   ├── VoskVoiceTrigger.kt   # Vosk offline speech recognition
│   │   │   │   │   └── BluetoothTrigger.kt   # BLE connection to ESP32
│   │   │   │   ├── receiver/
│   │   │   │   │   ├── PowerButtonReceiver.kt # Power button detection
│   │   │   │   │   └── BootReceiver.kt       # Auto-start on boot
│   │   │   │   ├── action/
│   │   │   │   │   └── EmergencyActions.kt   # SMS, Call, Location
│   │   │   │   └── util/
│   │   │   │       └── TriggerConfig.kt      # SharedPreferences storage
│   │   │   ├── res/
│   │   │   │   ├── layout/
│   │   │   │   │   ├── activity_main.xml
│   │   │   │   │   └── dialog_contacts.xml
│   │   │   │   ├── values/
│   │   │   │   │   ├── colors.xml
│   │   │   │   │   ├── strings.xml
│   │   │   │   │   └── themes.xml
│   │   │   │   └── drawable/...
│   │   │   └── AndroidManifest.xml
│   │   └── build.gradle.kts
│   ├── esp32/
│   │   └── esp32_ble_trigger.ino             # ESP32 companion code
│   ├── build.gradle.kts
│   ├── settings.gradle.kts
│   └── README.md
│
└── webhook/                              # PHP Backend
    ├── emergency.php                     # Main webhook + Dashboard
    ├── api.php                           # Additional API endpoints
    └── emergency_log.json                # Alert storage (auto-created)
```

---

## 🔧 Build Instructions

### Android App

**Option 1: Android Studio (Recommended)**
1. Open `c:\xampp\htdocs\App\EmergencyTrigger` in Android Studio
2. Wait for Gradle sync to complete
3. Build → Build APK(s)
4. APK output: `app/build/outputs/apk/debug/app-debug.apk`

**Option 2: Command Line**
```bash
cd c:\xampp\htdocs\App\EmergencyTrigger
.\gradlew assembleDebug
```

### ESP32 (Optional - for Bluetooth trigger)

1. Install [Arduino IDE](https://www.arduino.cc/en/software)
2. Add ESP32 board support:
   - File → Preferences → Additional Boards Manager URLs
   - Add: `https://dl.espressif.com/dl/package_esp32_index.json`
3. Install ESP32 boards from Board Manager
4. Open `esp32/esp32_ble_trigger.ino`
5. Select your ESP32 board and port
6. Upload

### PHP Webhook

The webhook is already in your XAMPP folder:
- **Dashboard**: http://localhost/App/webhook/emergency.php
- **API**: http://localhost/App/webhook/api.php

---

## 📱 App Permissions

| Permission | Purpose |
|------------|---------|
| `RECORD_AUDIO` | Voice trigger detection |
| `BLUETOOTH_CONNECT/SCAN` | ESP32 BLE connection |
| `SEND_SMS` | Emergency SMS alerts |
| `CALL_PHONE` | Emergency auto-call |
| `ACCESS_FINE_LOCATION` | Include location in SMS |
| `POST_NOTIFICATIONS` | Alert notifications |
| `FOREGROUND_SERVICE` | Background operation |

---

## ⚙️ Configuration

### In-App Settings

1. **Triggers**: Toggle each trigger on/off
2. **SMS Alert**: Enable and add emergency contacts
3. **Emergency Call**: Enable and set phone number
4. **Webhook URL**: Set your server endpoint

### Webhook URL Examples

```
# Local XAMPP (for testing)
http://192.168.1.100/App/webhook/emergency.php

# Public server
https://your-domain.com/webhook/emergency.php
```

### Customization (Code)

**Voice Trigger** (`VoiceTrigger.kt`):
```kotlin
private const val KEYWORD_COUNT = 3          // Number of speech patterns
private const val MAX_INTERVAL_MS = 1500L    // Time window
private const val ENERGY_THRESHOLD = 3000    // Voice sensitivity
```

**Power Button** (`PowerButtonReceiver.kt`):
```kotlin
private const val REQUIRED_PRESSES = 4       // Number of presses
private const val TIME_WINDOW_MS = 3000L     // Time window
```

**ESP32 BLE UUIDs** (`BluetoothTrigger.kt` + `esp32_ble_trigger.ino`):
```kotlin
// Must match on both Android and ESP32
private val SERVICE_UUID = UUID.fromString("12345678-1234-1234-1234-123456789ABC")
private val CHAR_UUID = UUID.fromString("87654321-4321-4321-4321-CBA987654321")
```

---

## 📡 Webhook Payload

When triggered, the app sends a POST request:

```json
{
  "trigger_source": "VOICE",
  "timestamp": 1736541797000,
  "device_id": "Pixel 8",
  "emergency": true
}
```

### Response

```json
{
  "success": true,
  "message": "Emergency trigger received",
  "alert_id": "alert_678abc123"
}
```

---

## 📱 SMS Message Format

```
🚨 EMERGENCY ALERT 🚨

This is an automated emergency message.
Trigger: VOICE

📍 Location: https://maps.google.com/?q=28.6139,77.2090

Please check on me immediately!

Sent at: 23:15:00 10/01/2026
```

---

## 🔌 ESP32 Hardware Setup

```
ESP32 GPIO4 ────┬──── Push Button ──── GND
                │
         (internal pullup)

ESP32 GPIO2 ──── Built-in LED (connection status)
```

### Button Behavior
- LED OFF: Waiting for connection
- LED ON: Android app connected
- LED BLINK (3x): Button pressed, trigger sent

---

## 🎤 Voice Recognition Options

### Option 1: Simple Energy Detection (Default)
- Detects 3 speech patterns in quick succession
- Works offline, no model needed
- Less accurate but lightweight

### Option 2: Vosk Offline Recognition (Advanced)
- Accurate keyword detection ("hello hello hello")
- Works completely offline after model download
- ~50MB model download

To enable Vosk:
1. Uncomment in `build.gradle.kts`:
   ```kotlin
   implementation("com.alphacephei:vosk-android:0.3.47")
   ```
2. Download model: [vosk-model-small-en-us](https://alphacephei.com/vosk/models)
3. Place in `assets/model-small-en-us/`
4. Uncomment VoskVoiceTrigger in TriggerService.kt

---

## 📊 PHP Dashboard Features

Visit `http://localhost/App/webhook/emergency.php`:

- **Real-time status**: Shows if system received recent alerts
- **Statistics**: Count by trigger type
- **Alert history**: Last 20 alerts with details
- **Auto-refresh**: Updates every 10 seconds

### Email Notifications

Edit `emergency.php`:
```php
define('NOTIFICATION_EMAIL', 'your-email@example.com');
```

### Telegram Notifications

1. Create a bot via [@BotFather](https://t.me/BotFather)
2. Get your chat ID via [@userinfobot](https://t.me/userinfobot)
3. Edit `emergency.php`:
```php
define('TELEGRAM_BOT_TOKEN', 'your-bot-token');
define('TELEGRAM_CHAT_ID', 'your-chat-id');
```

---

## ⚡ Features Summary

| Feature | Status |
|---------|--------|
| 🎤 Voice trigger | ✅ |
| 🔘 Power button trigger | ✅ |
| 📡 ESP32 Bluetooth trigger | ✅ |
| 📳 SOS vibration | ✅ |
| 🔔 Alert notifications | ✅ |
| 📱 SMS to multiple contacts | ✅ |
| 📍 Location in SMS | ✅ |
| 📞 Emergency auto-call | ✅ |
| 🌐 Webhook integration | ✅ |
| 🚀 Auto-start on boot | ✅ |
| 🔋 Background service | ✅ |
| 📊 PHP Dashboard | ✅ |
| 📧 Email notifications | ✅ |
| 📱 Telegram notifications | ✅ |

---

## 🔒 Important Notes

1. **Battery Optimization**: Disable battery optimization for the app in Android Settings → Apps → Emergency Trigger → Battery → Unrestricted

2. **Power Button Limitations**: Due to Android security, power button detection uses SCREEN_ON/OFF broadcasts. Some devices may behave differently.

3. **SMS/Call Costs**: SMS and calls use your phone's carrier. Standard rates apply.

4. **Location Accuracy**: Location requires GPS or network. May take a moment to acquire.

5. **Bluetooth Range**: ESP32 BLE typically works within 10-30 meters.

---

## 📜 License

MIT License - Free to use and modify.
