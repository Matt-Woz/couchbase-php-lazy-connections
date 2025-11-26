import json
import os
import sys
import matplotlib.pyplot as plt

results_dir = sys.argv[1]
charts_dir = os.path.join(results_dir, "charts")
os.makedirs(charts_dir, exist_ok=True)

experiments = []
for fname in os.listdir(results_dir):
    if fname.endswith(".json"):
        with open(os.path.join(results_dir, fname)) as f:
            experiments.append(json.load(f))

def get_latency(exp, lazy_key, conc):
    """
    Returns avg latency (float) or None if experiment has no GET ops.
    """
    lazy_section = exp["results"].get(lazy_key, {})
    conc_data = lazy_section.get(str(conc))
    if not conc_data:
        return None

    lat = conc_data.get("latency", {})
    return lat.get("avg_latency_ms")


all_concurrency = set()
for e in experiments:
    for c in e["experiment"]["concurrency_levels"]:
        all_concurrency.add(c)

all_concurrency = sorted(all_concurrency)

# KV connection delta charts
for conc in all_concurrency:
    exp_names = []
    lazy_false_vals = []
    lazy_true_vals = []

    for exp in experiments:
        name = exp["experiment"]["name"]
        r = exp["results"]

        if str(conc) not in r["lazy_false"] or str(conc) not in r["lazy_true"]:
            continue

        delta_false = r["lazy_false"][str(conc)]["metrics_delta"]["overall"]
        delta_true  = r["lazy_true"][str(conc)]["metrics_delta"]["overall"]

        exp_names.append(name)
        lazy_false_vals.append(delta_false)
        lazy_true_vals.append(delta_true)

    if not exp_names:
        continue

    plt.figure(figsize=(11, 6))
    x = range(len(exp_names))
    w = 0.35

    plt.bar([i - w/2 for i in x], lazy_false_vals, w, label="lazy = false")
    plt.bar([i + w/2 for i in x], lazy_true_vals, w, label="lazy = true")

    plt.title(f"KV Connection Delta — Concurrency {conc}")
    plt.xticks(x, exp_names, rotation=30, ha="right")
    plt.ylabel("Total KV Connection Delta")
    plt.legend()
    plt.tight_layout()

    out = os.path.join(charts_dir, f"delta_concurrency_{conc}.png")
    plt.savefig(out)
    plt.close()

# Average latency charts
for conc in all_concurrency:
    exp_names = []
    lat_false = []
    lat_true = []

    for exp in experiments:
        name = exp["experiment"]["name"]
        lf = get_latency(exp, "lazy_false", conc)
        lt = get_latency(exp, "lazy_true", conc)

        if lf is None or lt is None:
            continue

        exp_names.append(name)
        lat_false.append(lf)
        lat_true.append(lt)

    if not exp_names:
        print(f"No latency data for concurrency {conc}, skipping.")
        continue

    plt.figure(figsize=(11, 6))
    x = range(len(exp_names))
    w = 0.35

    plt.bar([i - w/2 for i in x], lat_false, w, label="lazy = false")
    plt.bar([i + w/2 for i in x], lat_true, w, label="lazy = true")

    plt.title(f"Average Operation Latency (ms) — Concurrency {conc}")
    plt.xticks(x, exp_names, rotation=30, ha="right")
    plt.ylabel("Latency (ms)")
    plt.legend()
    plt.tight_layout()

    out = os.path.join(charts_dir, f"latency_concurrency_{conc}.png")
    plt.savefig(out)
    plt.close()

# Per node latency charts
for conc in all_concurrency:

    for exp in experiments:
        name = exp["experiment"]["name"]
        res = exp["results"]

        lf = res.get("lazy_false", {}).get(str(conc))
        lt = res.get("lazy_true", {}).get(str(conc))

        if not lf or not lt:
            continue

        def per_node_avg(section):
            by_node = {}
            records = section.get("latency", {}).get("latency_records", [])
            for rec in records:
                idx = rec["node_index"]
                by_node.setdefault(idx, []).append(rec["latency_ms"])

            return {n: sum(v)/len(v) for n, v in by_node.items()}

        lf_by = per_node_avg(lf)
        lt_by = per_node_avg(lt)

        nodes = sorted(set(lf_by.keys()) & set(lt_by.keys()))
        if not nodes:
            print(f"Skipping per-node latency plot for {name} at conc={conc}")
            continue

        lf_vals = [lf_by[n] for n in nodes]
        lt_vals = [lt_by[n] for n in nodes]

        plt.figure(figsize=(11, 6))
        x = range(len(nodes))
        w = 0.35

        plt.bar([i - w/2 for i in x], lf_vals, w, label="lazy=false")
        plt.bar([i + w/2 for i in x], lt_vals, w, label="lazy=true")

        plt.title(f"Per-Node Latency — {name} (Concurrency {conc})")
        plt.xticks(x, [f"Node {n}" for n in nodes])
        plt.ylabel("Avg Latency (ms)")
        plt.legend()
        plt.tight_layout()

        out = os.path.join(charts_dir, f"latency_per_node_{name}_conc_{conc}.png")
        plt.savefig(out)
        plt.close()
