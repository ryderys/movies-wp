#!/usr/bin/env python3
"""Append missing movies-wp fa_IR translations for Content Automation."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "wp-content/plugins/custom-plugins/movies-wp-media-automation"
L10N = PLUGIN / "languages/movies-wp-fa_IR.l10n.php"

FA = {
	"Add trailers": "افزودن تریلر",
	"Allow Telegram publishing": "اجازه انتشار در تلگرام",
	"Announce episodes": "اعلام قسمت‌ها",
	"Announce movies": "اعلام فیلم‌ها",
	"Announce series": "اعلام سریال‌ها",
	"Auto-sync when new episodes appear in this directory": "همگام‌سازی خودکار وقتی قسمت‌های جدید در این پوشه ظاهر شوند",
	"Choose a trailer file first.": "ابتدا یک فایل تریلر انتخاب کنید.",
	"Configure this title’s media directory before uploading a trailer.": "قبل از آپلود تریلر، پوشه رسانه این عنوان را تنظیم کنید.",
	"Configured": "پیکربندی‌شده",
	"Confirm the test message before sending it.": "قبل از ارسال، پیام آزمایشی را تأیید کنید.",
	"Could not checksum the trailer file.": "محاسبه چک‌سام فایل تریلر ممکن نشد.",
	"Could not create a series sync snapshot.": "ایجاد اسنپ‌شات همگام‌سازی سریال ممکن نشد.",
	"Could not enqueue the series sync job.": "قرار دادن کار همگام‌سازی سریال در صف ممکن نشد.",
	"Could not save %1$s image %2$s.": "ذخیره تصویر %1$s با شناسه %2$s ممکن نشد.",
	"Could not save the TMDb image gallery.": "ذخیره گالری تصاویر TMDb ممکن نشد.",
	"Credentials": "اعتبارنامه‌ها",
	"Delivery": "ارسال",
	"Delivery is paused. Movie, series, and episode imports continue, and queued announcements wait.": "ارسال متوقف است. درون‌ریزی فیلم، سریال و قسمت ادامه دارد و اعلام‌های صف‌شده منتظر می‌مانند.",
	"Details: %s": "جزئیات: %s",
	"Egress proxy": "پروکسی خروجی",
	"Episode EP%s has no season in filename. An explicit season number is required for import.": "قسمت EP%s در نام فایل فصل ندارد. برای درون‌ریزی باید شماره فصل صریح وارد شود.",
	"Episode media paths use more than one series directory.": "مسیرهای رسانه قسمت بیش از یک پوشه سریال را استفاده می‌کنند.",
	"Episode-only filenames require an explicit season number. Please specify the season number in the form.": "نام‌فایل‌های فقط-قسمت به شماره فصل صریح نیاز دارند. لطفاً شماره فصل را در فرم مشخص کنید.",
	"Error": "خطا",
	"Explicit season %1$s conflicts with filename season %2$s for %3$s.": "فصل صریح %1$s با فصل تشخیص‌داده‌شده از نام فایل %2$s برای %3$s در تضاد است.",
	"Explicit season number. Required when filenames do not identify a season (e.g. EP01.mkv). Must match filenames if season is present.": "شماره فصل صریح. وقتی نام فایل فصل را مشخص نمی‌کند (مثلاً EP01.mkv) الزامی است. اگر فصل در نام فایل باشد باید با آن یکی باشد.",
	"Failed to save discovered trailer link.": "ذخیره لینک تریلر کشف‌شده ناموفق بود.",
	"Failed to save the TV show media directory.": "ذخیره پوشه رسانه سریال ناموفق بود.",
	"Global publishing": "انتشار سراسری",
	"I confirm this test send.": "ارسال آزمایشی را تأیید می‌کنم.",
	"ID": "شناسه",
	"Insufficient capability for Telegram settings.": "دسترسی لازم برای تنظیمات تلگرام وجود ندارد.",
	"Invalid TV show.": "سریال نامعتبر است.",
	"Invalid Telegram settings nonce.": "کد امنیتی تنظیمات تلگرام معتبر نیست.",
	"Invalid movie.": "فیلم نامعتبر است.",
	"Invalid title.": "عنوان نامعتبر است.",
	"Invalid trailer filename.": "نام فایل تریلر معتبر نیست.",
	"MP4, WebM or MKV · Maximum %d MiB · each new filename is added": "MP4، WebM یا MKV · حداکثر %d مگابایت · هر نام فایل جدید اضافه می‌شود",
	"Movies": "فیلم‌ها",
	"No recent failures.": "خطای اخیری وجود ندارد.",
	"No trailer file was uploaded.": "هیچ فایل تریلری آپلود نشد.",
	"No usable episode media paths were found.": "هیچ مسیر رسانه قابل‌استفاده‌ای برای قسمت پیدا نشد.",
	"Not configured": "پیکربندی نشده",
	"Ongoing series": "سریال در حال پخش",
	"Ongoing series is missing a TMDb ID.": "سریال در حال پخش شناسه TMDb ندارد.",
	"Ongoing series is missing a valid media directory.": "سریال در حال پخش پوشه رسانه معتبر ندارد.",
	"Ongoing series sync preview is not ready to import.": "پیش‌نمایش همگام‌سازی سریال در حال پخش برای درون‌ریزی آماده نیست.",
	"Only a failed Telegram job can be retried.": "فقط کار ناموفق تلگرام را می‌توان دوباره امتحان کرد.",
	"Pause delivery": "توقف ارسال",
	"Polls a cheap directory listing every 15 minutes. Full scan/import runs only when files change.": "هر ۱۵ دقیقه یک فهرست سبک از پوشه می‌گیرد. اسکن/درون‌ریزی کامل فقط وقتی فایل‌ها عوض شوند اجرا می‌شود.",
	"Post": "نوشته",
	"Publishing": "انتشار",
	"Queue": "صف",
	"Recent failures": "خطاهای اخیر",
	"Resume delivery": "ازسرگیری ارسال",
	"Retry": "تلاش مجدد",
	"Save Telegram settings": "ذخیره تنظیمات تلگرام",
	"Season number (optional)": "شماره فصل (اختیاری)",
	"Security check failed. Reload the page and try again.": "بررسی امنیتی ناموفق بود. صفحه را دوباره بارگذاری کنید و دوباره تلاش کنید.",
	"Send test message": "ارسال پیام آزمایشی",
	"Sends one labeled test message. It does not queue or publish a movie, series, or episode.": "یک پیام آزمایشی برچسب‌دار می‌فرستد. فیلم، سریال یا قسمتی را صف یا منتشر نمی‌کند.",
	"Series list authentication failed.": "احراز هویت فهرست سریال ناموفق بود.",
	"Series list failed.": "فهرست سریال ناموفق بود.",
	"Series list returned invalid data.": "فهرست سریال داده نامعتبر برگرداند.",
	"Streaming trailer upload requires PHP cURL with CURLFile support.": "آپلود جریانی تریلر به PHP cURL با پشتیبانی CURLFile نیاز دارد.",
	"TMDb image download is not available.": "دانلود تصویر TMDb در دسترس نیست.",
	"Telegram": "تلگرام",
	"Telegram admin action failed.": "عملیات مدیریتی تلگرام ناموفق بود.",
	"Telegram credentials are not configured.": "اعتبارنامه‌های تلگرام پیکربندی نشده‌اند.",
	"Telegram delivery can run again.": "ارسال تلگرام می‌تواند دوباره اجرا شود.",
	"Telegram delivery is paused. Imports are unchanged.": "ارسال تلگرام متوقف است. درون‌ریزی‌ها بدون تغییر ادامه دارند.",
	"Telegram did not accept the message.": "تلگرام پیام را نپذیرفت.",
	"Telegram settings saved.": "تنظیمات تلگرام ذخیره شد.",
	"Test message": "پیام آزمایشی",
	"Test message sent. No movie, series, or episode was queued.": "پیام آزمایشی ارسال شد. هیچ فیلم، سریال یا قسمتی صف نشد.",
	"The failed Telegram job was returned to the queue.": "کار ناموفق تلگرام به صف بازگردانده شد.",
	"The media server returned an invalid trailer path.": "سرور رسانه مسیر تریلر نامعتبر برگرداند.",
	"The selected file exceeds the maximum allowed size.": "فایل انتخاب‌شده از حداکثر اندازه مجاز بیشتر است.",
	"The series list request returned an unexpected HTTP status.": "درخواست فهرست سریال وضعیت HTTP غیرمنتظره برگرداند.",
	"The series list response was missing required fields.": "پاسخ فهرست سریال فیلدهای لازم را نداشت.",
	"The series list response was not valid JSON.": "پاسخ فهرست سریال JSON معتبر نبود.",
	"The specified season number conflicts with a season number detected in a filename.": "شماره فصل واردشده با شماره فصل تشخیص‌داده‌شده از نام فایل در تضاد است.",
	"The test message was blocked because it touched the import queue.": "پیام آزمایشی مسدود شد چون به صف درون‌ریزی دست زد.",
	"The trailer file could not be read.": "خواندن فایل تریلر ممکن نشد.",
	"The trailer file exceeds the maximum allowed size.": "فایل تریلر از حداکثر اندازه مجاز بیشتر است.",
	"The trailer file is empty.": "فایل تریلر خالی است.",
	"The trailer upload request failed.": "درخواست آپلود تریلر ناموفق بود.",
	"The trailer upload request timed out.": "زمان درخواست آپلود تریلر به پایان رسید.",
	"The trailer upload response was not valid JSON.": "پاسخ آپلود تریلر JSON معتبر نبود.",
	"The trailer upload returned an unexpected HTTP status.": "آپلود تریلر وضعیت HTTP غیرمنتظره برگرداند.",
	"This title has no media directory. Set _media_directory before uploading a trailer.": "این عنوان پوشه رسانه ندارد. قبل از آپلود تریلر، _media_directory را تنظیم کنید.",
	"Trailer must be an MP4, WebM, or MKV file with a safe name.": "تریلر باید فایل MP4، WebM یا MKV با نام امن باشد.",
	"Trailer type is not allowed.": "نوع تریلر مجاز نیست.",
	"Trailer upload authentication failed.": "احراز هویت آپلود تریلر ناموفق بود.",
	"Trailer upload failed.": "آپلود تریلر ناموفق بود.",
	"Trailer uploaded and link saved.": "تریلر آپلود و لینک ذخیره شد.",
	"Trailer was uploaded but saving the trailer link failed.": "تریلر آپلود شد اما ذخیره لینک تریلر ناموفق بود.",
	"Type": "نوع",
	"Unknown Telegram admin action.": "عملیات مدیریتی تلگرام ناشناخته است.",
	"Uploading…": "در حال آپلود…",
	"You are not allowed to upload trailers for this TV show.": "اجازه آپلود تریلر برای این سریال را ندارید.",
	"You are not allowed to upload trailers for this movie.": "اجازه آپلود تریلر برای این فیلم را ندارید.",
}


def collect_source_strings() -> set[str]:
	pat = re.compile(
		r"(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*(['\"])(.*?)\1\s*,\s*['\"]movies-wp['\"]",
		re.S,
	)
	out: set[str] = set()
	for path in PLUGIN.rglob("*.php"):
		if "tests" in path.parts or "languages" in path.parts:
			continue
		text = path.read_text(encoding="utf-8", errors="replace")
		for match in pat.finditer(text):
			out.add(match.group(2).replace("\\'", "'").replace('\\"', '"'))
	return out


def existing_keys(text: str) -> set[str]:
	msg = text[text.find("'messages'") :]
	keys: set[str] = set()
	for line in msg.split("\n"):
		line = line.strip()
		if "=>" not in line or not line.startswith("'"):
			continue
		match = re.match(r"'((?:\\'|[^'])*)'\s*=>", line)
		if match:
			keys.add(match.group(1).replace("\\'", "'"))
	keys.discard("messages")
	return keys


def php_escape(value: str) -> str:
	return value.replace("\\", "\\\\").replace("'", "\\'")


def main() -> None:
	text = L10N.read_text(encoding="utf-8")
	keys = existing_keys(text.replace("\r\n", "\n"))
	source = collect_source_strings()
	missing = sorted(s for s in source if s not in keys and s.strip())

	variants = dict(FA)
	for eng, per in list(FA.items()):
		variants[eng.replace("\u2019", "'")] = per
		variants[eng.replace("…", "...")] = per

	lines: list[str] = []
	unmapped: list[str] = []
	for eng in missing:
		per = variants.get(eng)
		if not per:
			unmapped.append(eng)
			continue
		lines.append(f"\t\t'{php_escape(eng)}' => '{php_escape(per)}',")

	if unmapped:
		print(f"UNMAPPED {len(unmapped)}")
		for item in unmapped:
			print(repr(item))
		raise SystemExit(1)

	text = text.replace(
		"'po-revision-date'          => '2026-08-16 00:00+0000',",
		"'po-revision-date'          => '2026-10-20 00:00+0000',",
	)
	marker = "\t\t'Unavailable'"
	idx = text.rfind(marker)
	if idx < 0:
		raise SystemExit("Unavailable entry not found")
	close = text.find("\n\t),", idx)
	if close < 0:
		raise SystemExit("messages array close not found")
	block = "\n".join(lines) + "\n"
	new_text = text[:close] + "\n" + block + text[close:]
	L10N.write_text(new_text, encoding="utf-8", newline="\n")
	print(f"inserted {len(lines)} translations; remaining missing check:")

	keys2 = existing_keys(new_text.replace("\r\n", "\n"))
	still = sorted(s for s in source if s not in keys2 and s.strip())
	print(f"still_missing={len(still)}")
	for item in still[:20]:
		print(repr(item))


if __name__ == "__main__":
	main()
