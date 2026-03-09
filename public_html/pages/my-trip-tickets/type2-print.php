<?php
/**
 * LOKA - Print Type 2 Weekly Trip Ticket
 */
if (!defined('BASE_PATH'))
    exit;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Trip Ticket – DICT Region II (Type 2)</title>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@500&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --ink: #111111;
            --dark: #222222;
            --body: #333333;
            --label: #444444;
            --sub: #666666;
            --border: #bbbbbb;
            --border2: #888888;
            --accent: #003580;
            --stripe: #f4f6f9;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #d8d8d8;
            min-height: 100vh;
            padding: 20px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow-x: auto;
        }

        .controls {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
        }

        .btn {
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            padding: 9px 26px;
            border-radius: 4px;
            cursor: pointer;
            border: 2px solid var(--ink);
            transition: all .15s;
        }

        .btn-print {
            background: var(--ink);
            color: #fff;
        }

        .btn-print:hover {
            background: var(--accent);
            border-color: var(--accent);
        }

        /* TICKET */
        .ticket {
            width: 297mm;
            max-width: 100%;
            background: #fff;
            box-shadow: 0 6px 40px rgba(0, 0, 0, .18);
            border: 1px solid #bbb;
            flex-shrink: 0;
        }

        /* HEADER */
        .hdr {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            padding: 8px 14px 6px;
            border-bottom: 2px solid var(--ink);
            gap: 10px;
        }

        .hdr-left {
            font-size: 7.5px;
            color: var(--body);
            line-height: 1.6;
        }

        .hdr-left strong {
            font-size: 8.5px;
            color: var(--ink);
            font-weight: 700;
            display: block;
        }

        .hdr-center {
            text-align: center;
        }

        .hdr-title {
            font-weight: 700;
            font-size: 17px;
            letter-spacing: .09em;
            color: var(--ink);
            text-transform: uppercase;
            line-height: 1;
        }

        .hdr-bar {
            width: 32px;
            height: 2px;
            background: var(--accent);
            margin: 4px auto 3px;
        }

        .hdr-sub {
            font-size: 7.5px;
            color: var(--sub);
            letter-spacing: .08em;
            text-transform: uppercase;
            font-weight: 500;
        }

        .hdr-right {
            text-align: right;
            font-size: 7.5px;
            color: var(--body);
            line-height: 1.6;
        }

        .hdr-right .lbl {
            font-size: 7px;
            color: var(--sub);
            letter-spacing: .1em;
            text-transform: uppercase;
            font-weight: 600;
            display: block;
        }

        .hdr-right .tno {
            font-family: 'DM Mono', monospace;
            font-size: 11px;
            font-weight: 500;
            color: var(--accent);
            display: block;
            margin-top: 1px;
        }

        .hdr-right .base {
            font-size: 9px;
            font-weight: 600;
            color: var(--ink);
        }

        /* SECTION LABEL */
        .sec {
            font-size: 7.5px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--accent);
            padding: 3.5px 10px 3px;
            border-top: 1.5px solid var(--border2);
            border-bottom: 1px solid var(--border);
            background: #f8f9fb;
        }

        /* INFO ROWS */
        .irow {
            display: flex;
            border-bottom: 1px solid var(--border);
        }

        .if {
            flex: 1;
            padding: 4px 9px 5px;
            border-right: 1px solid var(--border);
            min-height: 34px;
        }

        .if.f2 { flex: 2; }
        .if.f3 { flex: 3; }
        .if:last-child { border-right: none; }

        .if>.lbl {
            display: block;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--label);
            margin-bottom: 2px;
        }

        .if input, .if textarea {
            width: 100%;
            border: none;
            outline: none;
            font-family: 'DM Sans', sans-serif;
            font-size: 9.5px;
            font-weight: 500;
            color: var(--ink);
            background: transparent;
            padding: 0;
            line-height: 1.3;
        }

        /* TABLES */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            font-family: 'DM Sans', sans-serif;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--dark);
            text-align: center;
            vertical-align: middle;
            padding: 6px 4px;
            border: 1px solid var(--border2);
            border-bottom: 1.5px solid var(--ink);
            background: var(--white);
        }

        td {
            border: 1px solid var(--border);
            vertical-align: middle;
            padding: 0;
            background: var(--white);
        }

        td input {
            width: 100%;
            height: 100%;
            min-height: 26px;
            border: none;
            outline: none;
            font-family: 'DM Sans', sans-serif;
            font-size: 8.5px;
            font-weight: 400;
            color: var(--ink);
            background: transparent;
            padding: 4px 6px;
            text-align: center;
        }

        td input.left {
            text-align: left;
        }

        .tbl-wrap {
            border: 1.5px solid var(--border2);
        }

        /* Print styles */
        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm 10mm 20mm 10mm;
            }

            body {
                background: white;
                padding: 0;
            }

            .controls { display: none; }
            .ticket {
                box-shadow: none;
                border: none;
                width: 100%;
            }

            th, td { border-color: #000 !important; }
            input, textarea { color: #000 !important; }
            .sec { background: transparent !important; }
        }

        td[rowspan] input {
            height: auto;
            min-height: 40px !important;
        }

        tfoot td {
            font-size: 8px;
            font-weight: 600;
            color: var(--body);
            padding: 3px 6px;
            background: var(--white);
            border: 1px solid var(--border);
            border-top: 1.5px solid var(--border2);
        }

        /* SUMMARY */
        .summary {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            border-top: 1.5px solid var(--border2);
            border-bottom: 1.5px solid var(--border2);
        }

        .sum-c {
            padding: 5px 8px 6px;
            border-right: 1px solid var(--border);
            text-align: center;
        }

        .sum-c:last-child { border-right: none; }

        .sum-c>.lbl {
            display: block;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--label);
            margin-bottom: 3px;
            line-height: 1.35;
        }

        .sum-c input {
            width: 100%;
            border: none;
            border-bottom: 1px solid var(--border);
            outline: none;
            font-family: 'DM Mono', monospace;
            font-size: 10px;
            font-weight: 500;
            color: var(--ink);
            text-align: center;
            padding: 1px 0;
            background: transparent;
        }

        /* SIGNATORIES */
        .sigs {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            border-top: 1.5px solid var(--border2);
        }

        .sig {
            padding: 7px 16px 10px;
            border-right: 1px solid var(--border);
            text-align: center;
        }

        .sig:last-child { border-right: none; }

        .sig-role {
            font-size: 7px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--label);
            margin-bottom: 16px;
        }

        .sig-line {
            border-top: 1px solid var(--ink);
            margin: 0 10px 4px;
        }

        .sig-name {
            font-size: 9px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: .02em;
        }

        .sig-title {
            font-size: 7.5px;
            color: var(--sub);
            margin-top: 1px;
            font-weight: 500;
        }

        /* FOOTER */
        .ftr {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 12px;
            border-top: 2px solid var(--ink);
        }

        .ftr span {
            font-size: 7px;
            color: var(--sub);
            letter-spacing: .07em;
            text-transform: uppercase;
            font-weight: 500;
        }

        .ftr .ftr-tno {
            font-family: 'DM Mono', monospace;
            font-size: 9px;
            color: var(--accent);
            font-weight: 500;
        }
    </style>
</head>

<body>

    <div class="controls">
        <button class="btn btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
    </div>

    <div class="ticket">

        <!-- HEADER -->
        <div class="hdr">
            <div class="hdr-left">
                <strong>Republic of the Philippines</strong>
                Department of Information and<br>
                Communications Technology<br>
                Regional Office No. II
            </div>
            <div class="hdr-center">
                <div class="hdr-title">Vehicle Trip Ticket</div>
                <div class="hdr-bar"></div>
                <div class="hdr-sub">Motorpool Unit &nbsp;·&nbsp; Admin and Finance Division</div>
            </div>
            <div class="hdr-right">
                <span class="lbl">Trip No.</span>
                <span class="tno"><?= e($ticket->ticket_number) ?></span>
                <span class="lbl" style="margin-top:5px;">Location / Base</span>
                <span class="base">Tuguegarao City</span>
            </div>
        </div>

        <!-- VEHICLE INFORMATION -->
        <div class="sec">Vehicle Information</div>
        <div class="irow">
            <div class="if">
                <span class="lbl">Plate Number</span>
                <input type="text" value="<?= e($ticket->plate_number) ?>">
            </div>
            <div class="if f2">
                <span class="lbl">Make / Model</span>
                <input type="text" value="<?= e($ticket->make . ' ' . $ticket->model) ?>">
            </div>
            <div class="if">
                <span class="lbl">Fuel Type</span>
                <input type="text" value="<?= ucfirst(e($ticket->fuel_type ?? 'Diesel')) ?>">
            </div>
            <div class="if f2">
                <span class="lbl">Driver Assigned</span>
                <input type="text" value="<?= e($generatorName) ?>">
            </div>
            <div class="if f2">
                <span class="lbl">Trip No.</span>
                <input type="text" value="<?= e($ticket->ticket_number) ?>">
            </div>
        </div>

        <!-- TRIP INFORMATION -->
        <div class="sec">Trip Information (Weekly Summary)</div>
        <div class="irow">
            <div class="if f2">
                <span class="lbl">Week Number</span>
                <input type="text" value="Week <?= $ticket->week_number ?> (<?= formatDate($ticket->week_start, 'M/d/Y') ?> - <?= formatDate($ticket->week_end, 'M/d/Y') ?>)">
            </div>
            <div class="if f3">
                <span class="lbl">Purpose</span>
                <textarea rows="1">Weekly trip summary for vehicle <?= e($ticket->plate_number) ?>. Total trips: <?= $tripCount ?>, Total distance: <?= number_format($totalDistance) ?> km.</textarea>
            </div>
        </div>

        <!-- TRIP DETAILS -->
        <div class="sec">Trip Details (<?= count($trips) ?> Trips)</div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th width="10%">Date</th>
                        <th width="10%">Departure</th>
                        <th width="10%">Arrival</th>
                        <th width="8%">Odo Start</th>
                        <th width="8%">Odo End</th>
                        <th width="20%">Destination</th>
                        <th width="20%">Purpose</th>
                        <th width="14%">Driver/Passengers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trips as $t): ?>
                        <?php
                        $peopleCount = count($t->all_people);
                        $isFirstPerson = true;
                        foreach ($t->all_people as $personIdx => $person):
                        ?>
                            <tr>
                                <?php if ($isFirstPerson): ?>
                                    <td rowspan="<?= $peopleCount ?>"><input type="date" value="<?= date('Y-m-d', strtotime($t->start_date)) ?>"></td>
                                    <td rowspan="<?= $peopleCount ?>"><input type="time" value="<?= date('H:i', strtotime($t->start_date)) ?>"></td>
                                    <td rowspan="<?= $peopleCount ?>"><input type="time" value="<?= date('H:i', strtotime($t->end_date)) ?>"></td>
                                    <td rowspan="<?= $peopleCount ?>"><input type="text" value="<?= (int) $t->start_mileage ?>"></td>
                                    <td rowspan="<?= $peopleCount ?>"><input type="text" value="<?= (int) $t->end_mileage ?>"></td>
                                    <td rowspan="<?= $peopleCount ?>"><input class="left" type="text" value="<?= e($t->destination) ?>"></td>
                                    <td rowspan="<?= $peopleCount ?>"><input class="left" type="text" value="<?= e($t->purpose) ?>"></td>
                                <?php endif; ?>
                                <td><input class="left" type="text" value="<?= e($person['name']) ?> (<?= $person['role'] ?>)"></td>
                            </tr>
                            <?php $isFirstPerson = false; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- FUEL REFILLING DATA -->
        <div class="sec">Fuel Refilling Data</div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th width="10%">Qty (L)</th>
                        <th width="12%">Amount (₱)</th>
                        <th width="12%">Date</th>
                        <th width="30%">Additional Items</th>
                        <th width="36%">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fuelRefillData as $f): ?>
                        <tr>
                            <td><input type="text" value="<?= $f['qty'] > 0 ? number_format($f['qty'], 2) : '' ?>"></td>
                            <td><input type="text" value="<?= $f['amount'] > 0 ? number_format($f['amount'], 2) : '' ?>"></td>
                            <td><input type="date" value="<?= $f['date'] ?>"></td>
                            <td><input class="left" type="text" value="<?= e($f['items']) ?>"></td>
                            <td><input class="left" type="text" value="<?= e($f['remarks']) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-end" style="text-align: right; padding-right: 10px;"><strong>Total:</strong></td>
                        <td><?= number_format($totalFuel, 2) ?> L</td>
                        <td colspan="2">₱<?= number_format($totalCost, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- SUMMARY -->
        <div class="sec"></div>
        <div class="summary">
            <div class="sum-c">
                <span class="lbl">Balance Start</span>
                <input type="text" value="<?= $balanceStart ?>">
            </div>
            <div class="sum-c">
                <span class="lbl">Total Fuel Loaded</span>
                <input type="text" value="<?= number_format($totalFuel, 2) ?> L">
            </div>
            <div class="sum-c">
                <span class="lbl">Total Fuel Consumed</span>
                <input type="text" value="<?= number_format($totalFuel, 2) ?> L">
            </div>
            <div class="sum-c">
                <span class="lbl">Total Distance</span>
                <input type="text" value="<?= number_format($totalDistance) ?> km">
            </div>
            <div class="sum-c">
                <span class="lbl">Balance End</span>
                <input type="text" value="<?= $balanceEnd ?>">
            </div>
        </div>

        <!-- SIGNATORIES -->
        <div class="sec">Signatories</div>
        <div class="sigs">
            <div class="sig">
                <div class="sig-role">Prepared by</div>
                <div style="height:20px;"></div>
                <div class="sig-line"></div>
                <div class="sig-name"><?= e($generatorName) ?></div>
                <div class="sig-title">Driver / Caretaker</div>
            </div>
            <div class="sig">
                <div class="sig-role">Reviewed by</div>
                <div style="height:20px;"></div>
                <div class="sig-line"></div>
                <div class="sig-name">
                    <?php if ($ticket->reviewed_by): ?>
                        <?= e($ticket->reviewed_by_name) ?>
                    <?php else: ?>
                        (Pending Review)
                    <?php endif; ?>
                </div>
                <div class="sig-title">
                    <?php if ($ticket->approval_by === 'motorpool_head'): ?>
                        Motorpool Head
                    <?php else: ?>
                        Department Approver
                    <?php endif; ?>
                </div>
            </div>
            <div class="sig">
                <div class="sig-role">Approved</div>
                <div style="height:20px;"></div>
                <div class="sig-line"></div>
                <div class="sig-name">
                    <?php if ($ticket->status === 'approved'): ?>
                        ✓ Approved
                    <?php else: ?>
                        (Pending Approval)
                    <?php endif; ?>
                </div>
                <div class="sig-title">
                    <?php if ($ticket->approval_by === 'motorpool_head'): ?>
                        Motorpool Head
                    <?php else: ?>
                        Admin and Finance Division
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="ftr">
            <span>Department of Information and Communications Technology — Region II</span>
            <span class="ftr-tno">Trip No: <?= e($ticket->ticket_number) ?></span>
        </div>

    </div>

</body>
</html>
