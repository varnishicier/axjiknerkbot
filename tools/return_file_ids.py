# main.py
import os
from telegram import Update
from telegram.ext import (
    ApplicationBuilder,
    ContextTypes,
    MessageHandler,
    CommandHandler,
    filters,
)

def _fmt(kind: str, file_id: str, file_unique_id: str, extra: str = "") -> str:
    msg = f"{kind}\nfile_id: {file_id}\nfile_unique_id: {file_unique_id}"
    if extra:
        msg += f"\n{extra}"
    return msg

async def start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await update.message.reply_text(
        "Send me a photo/video/document and I will reply with file_id."
    )

async def media_handler(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    m = update.message
    if not m:
        return

    # Photo: Telegram sends multiple sizes; take the biggest (last).  [oai_citation:1‡Latenode Official Community](https://community.latenode.com/t/retrieving-original-sized-images-via-telegram-bot-api-from-user-uploads/8425?utm_source=chatgpt.com)
    if m.photo:
        p = m.photo[-1]
        await m.reply_text(_fmt("PHOTO", p.file_id, p.file_unique_id))
        return

    # Video
    if m.video:
        v = m.video
        await m.reply_text(_fmt("VIDEO", v.file_id, v.file_unique_id))
        return

    # Document (if you send media as a file)
    if m.document:
        d = m.document
        extra = f"file_name: {d.file_name or ''}"
        await m.reply_text(_fmt("DOCUMENT", d.file_id, d.file_unique_id, extra))
        return

    # Optional extras (handy sometimes)
    if m.animation:
        a = m.animation
        await m.reply_text(_fmt("ANIMATION", a.file_id, a.file_unique_id))
        return

    if m.audio:
        a = m.audio
        extra = f"file_name: {a.file_name or ''}"
        await m.reply_text(_fmt("AUDIO", a.file_id, a.file_unique_id, extra))
        return

    if m.voice:
        v = m.voice
        await m.reply_text(_fmt("VOICE", v.file_id, v.file_unique_id))
        return

    if m.video_note:
        vn = m.video_note
        await m.reply_text(_fmt("VIDEO_NOTE", vn.file_id, vn.file_unique_id))
        return

    await m.reply_text("Send a photo/video/document and I’ll return file_id.")

def main() -> None:
    token = ""
    if not token:
        raise SystemExit("Set BOT_TOKEN env var first")

    app = ApplicationBuilder().token(token).build()

    app.add_handler(CommandHandler("start", start))
    app.add_handler(
        MessageHandler(
            filters.PHOTO
            | filters.VIDEO
            | filters.Document.ALL
            | filters.ANIMATION
            | filters.AUDIO
            | filters.VOICE
            | filters.VIDEO_NOTE,
            media_handler,
        )
    )

    # Polling = easiest for macOS/local runs (no webhook needed)
    app.run_polling(allowed_updates=Update.ALL_TYPES)

if __name__ == "__main__":
    main()