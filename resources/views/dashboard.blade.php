<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Mesin</title>

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }
        .container { max-width: 1100px; margin: 0 auto; padding: 32px 16px; }
        header h1 { margin: 0 0 4px; font-size: 28px; }
        header p { margin: 0 0 24px; color: #64748b; }

        .summary {
            display: flex; flex-wrap: wrap; align-items: baseline; gap: 12px;
            background: #fff; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
        }
        .summary .label { color: #64748b; }
        .summary .value { font-size: 20px; font-weight: 700; }
        .summary .updated { margin-left: auto; font-size: 13px; color: #94a3b8; }

        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
        .card {
            background: #fff; border-radius: 12px; padding: 20px;
            border-top: 6px solid #cbd5e1;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
            transition: border-color .3s;
        }
        .card.on { border-top-color: #16a34a; }
        .card-head { display: flex; justify-content: space-between; align-items: center; }
        .card-head h2 { margin: 0; font-size: 20px; }
        .subtitle { font-size: 14px; color: #64748b; }

        .badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 999px;
            background: #f1f5f9; color: #475569;
        }
        .card.on .badge { background: #dcfce7; color: #15803d; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; }
        .card.on .dot { background: #16a34a; animation: pulse 1s infinite; }
        @keyframes pulse { 50% { opacity: .3; } }

        .timer {
            margin-top: 12px; font-size: 40px; font-weight: 700;
            font-variant-numeric: tabular-nums; letter-spacing: 1px;
        }
        .card.off .timer { color: #94a3b8; }

        .status-pill {
            display: inline-block; margin-top: 16px; padding: 6px 14px; border-radius: 8px;
            font-size: 14px; font-weight: 800; letter-spacing: 1px;
            background: #f1f5f9; color: #64748b;
        }
        .card.status-running { border-top-color: #16a34a; }
        .card.status-running .status-pill { background: #16a34a; color: #fff; }
        .card.status-mould { border-top-color: #ea580c; }
        .card.status-mould .status-pill { background: #ea580c; color: #fff; }
        .card.status-setter { border-top-color: #2563eb; }
        .card.status-setter .status-pill { background: #2563eb; color: #fff; }
        .card.status-off { border-top-color: #475569; }
        .card.status-off .status-pill { background: #475569; color: #fff; }
        .timer-label { font-size: 13px; color: #64748b; }
        .meta {
            display: flex; flex-direction: column; gap: 2px;
            margin-top: 16px; padding-top: 12px; border-top: 1px solid #e2e8f0;
            font-size: 13px; color: #64748b; overflow-wrap: anywhere;
        }
        .empty { color: #64748b; }
    </style>

    @livewireStyles
</head>
<body>
    <div class="container">
        <header>
            <h1>Dashboard Mesin</h1>
        </header>

        <livewire:mesin-monitor />
    </div>

    @livewireScripts
</body>
</html>
