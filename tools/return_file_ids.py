import re, json, pathlib

p = pathlib.Path("tools/new_ids.txt")
t = p.read_text(encoding="utf-8", errors="ignore")
ids = re.findall(r"file_id:\s*([A-Za-z0-9_-]+)", t)

print("Total:", len(ids))
uniq = list(dict.fromkeys(ids))  # сохраняет порядок
print("Unique:", len(uniq))
dups = len(ids) - len(uniq)
print("Duplicates:", dups)

print("\nJSON:")
print(json.dumps(uniq, ensure_ascii=False))
