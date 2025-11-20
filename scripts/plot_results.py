import json
import os
import sys
import matplotlib.pyplot as plt

results_dir = sys.argv[1]
charts_dir = os.path.join(results_dir, "charts")
os.makedirs(charts_dir, exist_ok=True)

experiments = []
for filename in os.listdir(results_dir):
    if filename.endswith(".json"):
        with open(os.path.join(results_dir, filename)) as f:
            experiments.append(json.load(f))

all_concurrency_levels = set()
for exp in experiments:
    for c in exp["experiment"]["concurrency_levels"]:
        all_concurrency_levels.add(c)

all_concurrency_levels = sorted(all_concurrency_levels)

for concurrency in all_concurrency_levels:
    exp_names = []
    lazy_false_vals = []
    lazy_true_vals = []

    for exp in experiments:
        name = exp["experiment"]["name"]
        result = exp["results"]

        exp_names.append(name)

        lf = result["lazy_false"][str(concurrency)]["metrics_delta"]["overall"]
        lt = result["lazy_true"][str(concurrency)]["metrics_delta"]["overall"]

        lazy_false_vals.append(lf)
        lazy_true_vals.append(lt)

    plt.figure(figsize=(10, 6))

    x = range(len(exp_names))
    width = 0.35

    plt.bar([i - width/2 for i in x], lazy_false_vals, width, label="lazy = false")
    plt.bar([i + width/2 for i in x], lazy_true_vals, width, label="lazy = true")

    plt.title(f"KV Connection Delta — Concurrency {concurrency}")
    plt.xticks(x, exp_names, rotation=30, ha="right")
    plt.ylabel("Total Connection Delta")
    plt.legend()
    plt.tight_layout()

    outpath = os.path.join(charts_dir, f"chart_concurrency_{concurrency}.png")
    plt.savefig(outpath)
    plt.close()
