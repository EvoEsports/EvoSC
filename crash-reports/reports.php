<?php

include '../vendor/autoload.php';

if (isset($_GET['report'])) {
    $report           = json_decode(file_get_contents(__DIR__ . '/' . $_GET['report']));
    $report->filename = $_GET['report'];
    $date             = date_create_from_format('Y-m-d_Hi', preg_replace('/\.json$/', '', $report->filename));
    $report->date     = $date->format('d.m.Y H:i');

    ?>

    <html>
    <head>
        <title>ESC Crash report - <?php echo $report->date; ?></title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-rc.4/js/uikit.min.js"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-rc.4/css/uikit.min.css">
    </head>
    <body>
        <div class="uk-padding">
            <h2>EvoSC crash report: <?php echo $report->date; ?></h2>

            <div class="uk-margin">
                <a href="/">Back</a>
            </div>

            <div class="uk-margin uk-text-bold">
                <?php echo $report->file; ?>: <?php echo $report->line; ?>
            </div>

            <div class="uk-margin">
                <code>
                    <?php echo str_replace("\n", '<br>', $report->trace); ?>
                </code>
            </div>
        </div>
    </body>
    </html>

    <?php
} else {
    $files = collect(scandir(__DIR__));

    $files = $files->filter(function ($file) {
        return preg_match('/^\d{4}-\d{2}-\d{2}_\d{4}\.json$/', $file);
    });

    $reports = $files->map(function ($filename) {
        $report           = json_decode(file_get_contents($filename));
        $date             = date_create_from_format('Y-m-d_Hi', preg_replace('/\.json$/', '', $filename));
        $report->date     = $date->format('d.m.Y H:i');
        $report->filename = $filename;
        return $report;
    });

    ?>
    <html>
    <head>
        <title>ESC Crash reports</title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-rc.4/js/uikit.min.js"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-rc.4/css/uikit.min.css">
    </head>
    <body>
        <div class="uk-padding">
            <h2>EvoSC crash reports</h2>

            <div>
                <table class="uk-table uk-table-expand uk-table-striped">
                    <thead>
                    <th>Date</th>
                    <th>File</th>
                    <th>Message</th>
                    <th>First line of trace</th>
                    </thead>
                    <tbody>
                    <?php foreach ($reports as $report) { ?>
                        <tr>
                            <td><?php echo $report->date; ?></td>
                            <td><?php echo $report->file; ?></td>
                            <td><?php echo $report->message; ?></td>
                            <td><?php echo substr($report->trace, 0, strpos($report->trace, "\n")); ?></td>
                            <td><a href="/?report=<?php echo $report->filename; ?>">Details</a></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>



