<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Details - LeoBot Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 24px;
            color: #222;
        }
        .container {
            max-width: 760px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }
        h1 { margin-top: 0; }
        .field {
            padding: 14px 0;
            border-bottom: 1px solid #eee;
        }
        .label {
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
        }
        .value {
            font-size: 16px;
            overflow-wrap: anywhere;
        }
        .back {
            display: inline-block;
            margin-bottom: 18px;
            color: #1557b0;
            text-decoration: none;
        }
        .notice {
            margin-top: 20px;
            color: #666;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="container">
    <a class="back" href="{{ route('admin.members.index') }}">
        &larr; Back to Members
    </a>

    <div class="card">
        <h1>Member Details</h1>

        <div class="field">
            <div class="label">Username</div>
            <div class="value">{{ $member->username }}</div>
        </div>

        <div class="field">
            <div class="label">Name</div>
            <div class="value">{{ $member->name }}</div>
        </div>

        <div class="field">
            <div class="label">Sponsor Username</div>
            <div class="value">{{ $member->sponsor?->username ?? 'N/A' }}</div>
        </div>

        <div class="field">
            <div class="label">Direct Referrals</div>
            <div class="value">{{ $member->referrals_count }}</div>
        </div>

        <div class="field">
            <div class="label">Account Status</div>
            <div class="value">{{ $member->status }}</div>
        </div>

        <div class="field">
            <div class="label">Registration Date</div>
            <div class="value">
                {{ $member->created_at?->format('d M Y, h:i A') ?? 'N/A' }}
            </div>
        </div>

        <p>
            <a href="{{ route('admin.members.referrals', $member->id) }}">
                View Direct Referrals
            </a>
        </p>
        <p class="notice">
            Read-only member information. No account or financial changes
            can be performed from this page.
        </p>
    </div>
</div>
</body>
</html>