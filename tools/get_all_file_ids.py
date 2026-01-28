import re
from collections import Counter

with open("ids.txt", "r", encoding="utf-8") as f:
    txt = f.read()

ids = re.findall(r'file_id:\s*([A-Za-z0-9_-]+)', txt)

print("Total file_id:", len(ids))
c = Counter(ids)
dups = [k for k, v in c.items() if v > 1]
print("Unique:", len(c))
print("Duplicates count:", len(dups))

if dups:
    print("\nDUPLICATES:")
    for k in dups:
        print(k, "x", c[k])

dedup = list(dict.fromkeys(ids))  # keeps order
print("\nJSON for VIDEO_SOURCES:")
print("[" + ",".join(f'"{x}"' for x in dedup) + "]")