"""Bridge Telegram Bot (@chili_vision_bot) ke API Chatbot & Computer Vision Laravel."""
from __future__ import annotations

import os
import re
import socket
import threading
import time
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

import requests
from dotenv import load_dotenv
from requests.adapters import HTTPAdapter
import urllib3.util.connection as urllib3_cn
from urllib3.util.retry import Retry

# Hotspot/ISP sering lambat atau gagal di rute IPv6 menuju api.telegram.org; paksa IPv4 agar stabil
urllib3_cn.allowed_gai_family = lambda: socket.AF_INET

# Cache DNS + fallback IP resmi api.telegram.org agar tahan terhadap gangguan DNS sesaat ([Errno 11001])
_ORIG_GETADDRINFO = socket.getaddrinfo
_DNS_CACHE: dict[str, str] = {"api.telegram.org": "149.154.166.110"}


def _resilient_getaddrinfo(host, port, family=0, type=0, proto=0, flags=0):
    try:
        res = _ORIG_GETADDRINFO(host, port, family, type, proto, flags)
        if host == "api.telegram.org" and res:
            for item in res:
                if item[0] == socket.AF_INET and item[4]:
                    _DNS_CACHE["api.telegram.org"] = item[4][0]
                    break
        return res
    except socket.gaierror:
        if host in _DNS_CACHE:
            return _ORIG_GETADDRINFO(_DNS_CACHE[host], port, socket.AF_INET, type, proto, flags)
        raise


socket.getaddrinfo = _resilient_getaddrinfo

PROJECT_ROOT = Path(__file__).resolve().parents[1]
ENV_PATH = PROJECT_ROOT / ".env"
load_dotenv(ENV_PATH, override=True)

TELEGRAM_BOT_TOKEN = os.getenv("TELEGRAM_BOT_TOKEN", "").strip()
LARAVEL_API_URL = os.getenv("APP_URL", "http://127.0.0.1:8000").rstrip("/") + "/api"


def create_http_session(connect_retries: int = 3) -> requests.Session:
    """Buat HTTP Session dengan connection pooling dan retry koneksi."""
    session = requests.Session()
    adapter = HTTPAdapter(
        pool_connections=10,
        pool_maxsize=20,
        # Hanya retry kegagalan koneksi (bukan respons 5xx/read) agar perintah semprot tidak terkirim ganda
        max_retries=Retry(total=connect_retries, connect=connect_retries, read=0, status=0, backoff_factor=0.5),
    )
    session.mount("https://", adapter)
    session.mount("http://", adapter)
    return session


# Pisahkan session untuk worker pengiriman pesan (HTTP) dan loop long-polling (POLL_HTTP)
HTTP = create_http_session(connect_retries=3)
POLL_HTTP = create_http_session(connect_retries=1)


def save_chat_id_to_env(chat_id: int | str) -> None:
    """Simpan TELEGRAM_CHAT_ID secara otomatis ke .env saat user pertama kali chat."""
    try:
        current = os.getenv("TELEGRAM_CHAT_ID", "").strip()
        if current == str(chat_id):
            return
        content = ENV_PATH.read_text(encoding="utf-8")
        if "TELEGRAM_CHAT_ID=" in content:
            content = re.sub(
                r"^TELEGRAM_CHAT_ID=.*$",
                f"TELEGRAM_CHAT_ID={chat_id}",
                content,
                flags=re.MULTILINE,
            )
        else:
            content += f"\nTELEGRAM_CHAT_ID={chat_id}\n"
        ENV_PATH.write_text(content, encoding="utf-8")
        os.environ["TELEGRAM_CHAT_ID"] = str(chat_id)
        print(f"[Info] TELEGRAM_CHAT_ID={chat_id} otomatis disimpan ke .env")
    except Exception as exc:
        print(f"[Warning] Gagal menyimpan TELEGRAM_CHAT_ID: {exc}")


def register_bot_commands(tg_base: str) -> None:
    """Daftarkan menu tombol command di aplikasi Telegram."""
    commands = [
        {"command": "status", "description": "Ringkasan kondisi daun, sensor, & histori semprot"},
        {"command": "semprot_air", "description": "Semprot air secara manual (5 detik)"},
        {"command": "semprot_pestisida", "description": "Semprot pestisida secara manual (5 detik)"},
        {"command": "cek_daun", "description": "Lihat deteksi gambar daun terakhir di database"},
        {"command": "cek_tanah", "description": "Lihat data terakhir sensor Soil Moisture"},
        {"command": "cek_suhu", "description": "Lihat data terakhir sensor DHT22"},
        {"command": "histori", "description": "Lihat waktu terakhir air & pestisida disemprot"},
        {"command": "model", "description": "Pilih model AI untuk analisis foto daun"},
    ]
    try:
        HTTP.post(f"{tg_base}/setMyCommands", json={"commands": commands}, timeout=10)
    except Exception as exc:
        print(f"[Warning] Gagal mendaftarkan menu command: {exc}")


def send_telegram_message(tg_base: str, chat_id: int | str, text: str, reply_markup: dict | None = None) -> None:
    """Kirim pesan ke Telegram dengan fallback tanpa parse_mode jika Markdown gagal di-parse."""
    clean_text = text.replace("**", "*")
    payload: dict = {
        "chat_id": chat_id,
        "text": clean_text,
        "parse_mode": "Markdown",
    }
    if reply_markup is not None:
        payload["reply_markup"] = reply_markup

    res = HTTP.post(f"{tg_base}/sendMessage", json=payload, timeout=10)
    if not res.ok:
        payload.pop("parse_mode", None)
        payload["text"] = clean_text.replace("*", "").replace("`", "")
        HTTP.post(f"{tg_base}/sendMessage", json=payload, timeout=10)


def normalize_command(text: str) -> str:
    # Buang suffix @nama_bot (mis. "/status@chili_vision_bot" di grup) dan argumen setelah command
    lower = re.sub(r"^(/\w+)@\w+", r"\1", text.lower().strip())
    mapping = {
        "/cek_daun": "cek daun",
        "/cek_tanah": "cek tanah",
        "/cek_suhu": "cek suhu",
        "/histori": "histori",
        "/start": "status",
        "/status": "status",
    }
    return mapping.get(lower, text)


def get_selected_model_id() -> str:
    """Ambil model yang dipilih dari API Laravel (default yolov8n-chili)."""
    try:
        res = HTTP.get(f"{LARAVEL_API_URL}/telegram/model", timeout=10)
        if res.ok:
            return res.json().get("selected_model_id", "yolov8n-chili")
    except Exception as exc:
        print(f"[Warning] Gagal mengambil model terpilih: {exc}")
    return "yolov8n-chili"


def build_model_inline_keyboard(models_list: list[dict]) -> dict | None:
    """Bangun inline keyboard Telegram dari daftar model API Laravel."""
    keyboard = []
    for model in models_list:
        if not model.get("available"):
            continue
        mark = "✅ " if model.get("selected") else ""
        keyboard.append([{"text": f"{mark}{model['name']}", "callback_data": f"select_model:{model['id']}"}])
    return {"inline_keyboard": keyboard} if keyboard else None


def send_model_menu(tg_base: str, chat_id: int | str) -> None:
    """Tampilkan daftar model sebagai tombol pilihan di Telegram."""
    try:
        res = HTTP.get(f"{LARAVEL_API_URL}/telegram/model", timeout=10)
        data = res.json()
    except Exception as exc:
        send_telegram_message(tg_base, chat_id, f"⚠️ Gagal mengambil daftar model: {exc}")
        return

    reply_markup = build_model_inline_keyboard(data.get("models", []))
    if not reply_markup:
        send_telegram_message(tg_base, chat_id, "⚠️ Belum ada model yang tersedia.")
        return

    send_telegram_message(
        tg_base,
        chat_id,
        "🤖 Pilih model AI untuk analisis foto daun:",
        reply_markup=reply_markup,
    )


def handle_model_selection(tg_base: str, cb_id: str, chat_id: int | str, message_id: int | None, model_id: str) -> None:
    """Simpan pilihan model ke API Laravel lalu beri konfirmasi."""
    try:
        res = HTTP.post(f"{LARAVEL_API_URL}/telegram/model", json={"model_id": model_id}, timeout=10)
        payload = res.json()
        message = payload.get("message", "Gagal mengganti model.")
        HTTP.post(
            f"{tg_base}/answerCallbackQuery",
            json={"callback_query_id": cb_id, "text": message},
            timeout=10,
        )
        if res.ok and message_id:
            HTTP.post(
                f"{tg_base}/editMessageReplyMarkup",
                json={"chat_id": chat_id, "message_id": message_id, "reply_markup": {"inline_keyboard": []}},
                timeout=10,
            )
        send_telegram_message(tg_base, chat_id, ("✅ " if res.ok else "⚠️ ") + message)
    except Exception as exc:
        print(f"[Error] Gagal memilih model: {exc}")


def handle_callback_query(tg_base: str, callback_query: dict) -> None:
    """Proses tombol konfirmasi Inline Keyboard (Ya / Tidak Semprot Pestisida) di Telegram."""
    cb_id = callback_query.get("id")
    data = callback_query.get("data", "")
    msg = callback_query.get("message", {})
    chat_id = msg.get("chat", {}).get("id")
    message_id = msg.get("message_id")

    if not chat_id:
        return

    save_chat_id_to_env(chat_id)

    if data.startswith("select_model:"):
        handle_model_selection(tg_base, cb_id, chat_id, message_id, data.split(":", 1)[1])
        return

    if data == "confirm_pesticide_yes":
        command_text = "ya"
        toast_text = "Konfirmasi diterima! Menyemprotkan pestisida..."
    elif data == "confirm_pesticide_no":
        command_text = "tidak"
        toast_text = "Penyemprotan pestisida dibatalkan."
    else:
        return

    try:
        HTTP.post(
            f"{tg_base}/answerCallbackQuery",
            json={"callback_query_id": cb_id, "text": toast_text},
            timeout=10,
        )

        # Hilangkan tombol inline pada pesan konfirmasi sebelumnya agar tidak diklik dua kali
        if message_id:
            HTTP.post(
                f"{tg_base}/editMessageReplyMarkup",
                json={"chat_id": chat_id, "message_id": message_id, "reply_markup": {"inline_keyboard": []}},
                timeout=10,
            )

        # Teruskan konfirmasi ke API Chatbot Laravel
        api_res = HTTP.post(
            f"{LARAVEL_API_URL}/chatbot/message",
            json={"message": command_text, "source": "telegram"},
            timeout=10,
        )
        reply_text = toast_text
        if api_res.ok:
            payload = api_res.json()
            reply_text = payload.get("bot_reply", {}).get("message", reply_text)

        send_telegram_message(tg_base, chat_id, reply_text)
    except Exception as exc:
        print(f"[Error] Gagal memproses callback query Telegram: {exc}")


def handle_photo_message(tg_base: str, chat_id: int | str, file_id: str, filename: str = "telegram_leaf.jpg") -> None:
    """Jika user mengirim foto daun cabai di Telegram (foto terkompresi atau dokumen gambar), teruskan ke API Gambar Daun Laravel."""
    try:
        file_info = HTTP.get(f"{tg_base}/getFile", params={"file_id": file_id}, timeout=10).json()
        file_path = file_info["result"]["file_path"]
        img_bytes = HTTP.get(
            f"https://api.telegram.org/file/bot{TELEGRAM_BOT_TOKEN}/{file_path}",
            timeout=15,
        ).content

        HTTP.post(
            f"{tg_base}/sendMessage",
            json={"chat_id": chat_id, "text": "🔍 Menganalisis foto daun cabai dengan AI..."},
            timeout=10,
        )

        selected_model = get_selected_model_id()
        api_res = HTTP.post(
            f"{LARAVEL_API_URL}/leaf-image",
            params={"model_id": selected_model, "threshold": "0.25"},
            files={"file": (filename, img_bytes, "image/jpeg")},
            timeout=25,
        )
        if api_res.ok:
            data = api_res.json()
            primary = data.get("primary_detection") or data.get("classification")
            db_msg = data.get("database_message", "")
            if not primary:
                reply = f"🍃 Tidak ada objek daun yang terdeteksi di atas threshold.\n• Status DB: {db_msg}"
                send_telegram_message(tg_base, chat_id, reply)
            # Jika objek terdeteksi, ChiliVisionApiController::predictAndStoreLeaf
            # sudah otomatis mengirim pesan hasil / tombol konfirmasi yang identik ke Website Chatbot & Telegram.
        else:
            send_telegram_message(tg_base, chat_id, "⚠️ Gagal menganalisis gambar daun di server Laravel.")
    except Exception as exc:
        print(f"[Error] Gagal memproses foto Telegram: {exc}")



def process_text_message(tg_base: str, chat_id: int | str, raw_text: str, bot_username: str) -> None:
    """Teruskan pesan teks/command ke Laravel lalu balas ke Telegram (dijalankan di worker thread)."""
    cleaned = raw_text.strip().lower().split("@")[0].strip()
    if cleaned in {"/model", "model", "pilih model", "ganti model", "daftar model", "cek model"}:
        send_model_menu(tg_base, chat_id)
        return

    text = normalize_command(raw_text)
    print(f"[Telegram @{bot_username} | Chat {chat_id}] Pesan: {raw_text}")

    reply_text = "Gagal memproses perintah di server Laravel."
    reply_markup: dict | None = None
    try:
        api_res = HTTP.post(
            f"{LARAVEL_API_URL}/chatbot/message",
            json={"message": text, "source": "telegram"},
            timeout=(5, 45),
        )
        if api_res.ok:
            payload = api_res.json()
            bot_reply = payload.get("bot_reply", {})
            reply_text = bot_reply.get("message", reply_text)
            if bot_reply.get("intent") == "select_model_menu":
                models_list = (bot_reply.get("metadata_json") or {}).get("models", [])
                reply_markup = build_model_inline_keyboard(models_list)
    except Exception as exc:
        print(f"[Error] Laravel tidak merespons: {exc}")
        reply_text = "⚠️ Server Laravel tidak merespons, coba lagi sesaat lagi."

    try:
        send_telegram_message(tg_base, chat_id, reply_text, reply_markup=reply_markup)
    except Exception as exc:
        print(f"[Error] Gagal membalas ke Telegram: {exc}")


def process_update(tg_base: str, item: dict, bot_username: str) -> None:
    """Proses satu update Telegram; semua error ditangkap agar tidak mengganggu update lain."""
    try:
        if "callback_query" in item:
            handle_callback_query(tg_base, item["callback_query"])
            return

        msg = item.get("message") or item.get("edited_message") or {}
        chat_id = msg.get("chat", {}).get("id")
        if not chat_id:
            return

        save_chat_id_to_env(chat_id)

        if "photo" in msg and msg["photo"]:
            handle_photo_message(tg_base, chat_id, msg["photo"][-1]["file_id"], "telegram_leaf.jpg")
            return

        doc = msg.get("document") or {}
        if isinstance(doc, dict) and str(doc.get("mime_type", "")).startswith("image/") and doc.get("file_id"):
            doc_name = str(doc.get("file_name") or "telegram_leaf.jpg")
            if not doc_name.lower().startswith("telegram_"):
                doc_name = f"telegram_{doc_name}"
            handle_photo_message(tg_base, chat_id, doc["file_id"], doc_name)
            return

        raw_text = (msg.get("text") or "").strip()
        if raw_text:
            process_text_message(tg_base, chat_id, raw_text, bot_username)
    except Exception as exc:
        print(f"[Error] Gagal memproses update {item.get('update_id')}: {exc}")


def main() -> None:
    global POLL_HTTP
    if not TELEGRAM_BOT_TOKEN:
        print("ERROR: TELEGRAM_BOT_TOKEN belum diisi di file .env!")
        return

    tg_base = f"https://api.telegram.org/bot{TELEGRAM_BOT_TOKEN}"

    bot_username = "chili_vision_bot"
    while True:
        try:
            me = POLL_HTTP.get(f"{tg_base}/getMe", timeout=(5, 15)).json()
            bot_username = me.get("result", {}).get("username", bot_username)
            POLL_HTTP.get(f"{tg_base}/deleteWebhook", timeout=(5, 15))
            break
        except Exception as exc:
            safe_err = re.sub(r"/bot[^/]+/", "/bot<TOKEN>/", str(exc))
            print(f"[Warning] Menunggu koneksi ke Telegram: {safe_err}")
            time.sleep(3)

    print(f"Telegram Bot @{bot_username} aktif! Terhubung ke {LARAVEL_API_URL}")
    register_bot_commands(tg_base)

    last_update_id = 0
    consecutive_net_errors = 0
    # Worker thread: pesan lambat (foto/AI) tidak menghambat command lain, dan callback tombol tetap cepat
    executor = ThreadPoolExecutor(max_workers=8)

    while True:
        try:
            # Gunakan long-poll 10 detik (bukan 30 detik) agar koneksi idle tidak diputus oleh NAT/Hotspot
            res = POLL_HTTP.get(
                f"{tg_base}/getUpdates",
                params={
                    "offset": last_update_id + 1,
                    "timeout": 10,
                    "allowed_updates": '["message","edited_message","callback_query"]',
                },
                timeout=(5, 16),
            )
            if consecutive_net_errors >= 3:
                print("[Info] Koneksi ke Telegram kembali normal.")
            consecutive_net_errors = 0

            data = res.json()
            if not data.get("ok"):
                err_code = data.get("error_code")
                desc = data.get("description", "Unknown Telegram API error")
                print(f"[Warning] Telegram getUpdates error {err_code}: {desc}")
                time.sleep(3)
                continue
            for item in data.get("result", []):
                last_update_id = max(last_update_id, item["update_id"])
                executor.submit(process_update, tg_base, item, bot_username)
        except KeyboardInterrupt:
            print("\nTelegram Bot dihentikan.")
            executor.shutdown(wait=False, cancel_futures=True)
            break
        except requests.exceptions.ReadTimeout:
            # Jika soket idle terputus oleh NAT/Hotspot saat long-polling, segarkan session tanpa spam warning
            try:
                POLL_HTTP.close()
            except Exception:
                pass
            POLL_HTTP = create_http_session(connect_retries=1)
        except Exception as exc:
            consecutive_net_errors += 1
            try:
                POLL_HTTP.close()
            except Exception:
                pass
            POLL_HTTP = create_http_session(connect_retries=1)
            if consecutive_net_errors == 3 or consecutive_net_errors % 10 == 0:
                safe_err = re.sub(r"/bot[^/]+/", "/bot<TOKEN>/", str(exc))
                print(f"[Warning] Gangguan jaringan ke Telegram ({consecutive_net_errors}x): {safe_err}")
            time.sleep(min(5, 1 + consecutive_net_errors))


if __name__ == "__main__":
    main()