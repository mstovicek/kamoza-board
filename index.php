<?php

/**
 * ?reset - shows form to set Jira password
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

session_start();

if (isset($_GET['reset']) || !isset($_SESSION['username']) || !isset($_SESSION['password'])) {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $_SESSION['username'] = $_POST['username'];
        $_SESSION['password'] = $_POST['password'];
        header('location: ?');
    }

    echo <<<EOF
<form method="post">
    <label for="username">Jira username:</label>
    <input type="text" name="username" />
    <label for="password">Password:</label>
    <input type="password" name="password" />
    <input type="submit" value="Save" />
</form>
EOF;
    die();
}

$api = new chobie\Jira\Api(
    'https://jira.rocket-internet.de',
    new chobie\Jira\Api\Authentication\Basic($_SESSION['username'], $_SESSION['password'])
);

$tickets = [
    'backlog' => [],
    'in-dev' => [],
    'code-review' => [],
    'done' => [],
];

$columnNames = [
    'backlog' => 'Backlog',
    'in-dev' => 'In Development',
    'code-review' => 'CR / QA',
    'done' => 'Done',
];

$epicLinks = [];

$walker = new chobie\Jira\Issues\Walker($api);
$walker->push('cf[10900] = 13 AND sprint in openSprints() AND sprint NOT IN futureSprints()');
foreach ($walker as $issue) {
    /** @var chobie\Jira\Issue $issue */
    $labels = $issue->getLabels();
    switch (true) {
        case 'Backlog' == $issue->getStatus()['name']:
            $tickets['backlog'][] = $issue;
            break;
        case 'In Development' == $issue->getStatus()['name'] && in_array('cr', $labels):
            $tickets['code-review'][] = $issue;
            break;
        case 'In Development' == $issue->getStatus()['name']:
            $tickets['in-dev'][] = $issue;
            break;
        default:
            $tickets['done'][] = $issue;
    }

    if (null !== ($epicLink = $issue->get('Epic Link'))) {
        $epicLinks[$epicLink] = $epicLink;
    }
}

$epics = $epicLinks;

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="600">
    <title>KAMOZA</title>
    <link rel="stylesheet" href="vendor/twbs/bootstrap/dist/css/bootstrap.min.css"/>
    <style type="text/css">
        .issue {
            padding: 0 5% 2%;
            margin: 10px 0;
            border: 1px solid black;
        }
        .issue.type-6 { /* story */
            background-color: #ffffcc;
        }
        .assignee {
            margin-top: 10px;
        }
        h2 {
            margin: 20px 0;
        }
        .epic {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <h1>KAMOZA Sprint board</h1>

    <div class="container-fluid">
    <div class="row">
    <?php foreach ($tickets as $columnName => $colTickets) : ?>
        <div class="col-md-3 column <?php print $columnName ?>">

            <h2><?php print $columnNames[$columnName] ?></h2>
        <?php
        foreach ($colTickets as $issue) :
        /** @var chobie\Jira\Issue $issue */
        ?>
            <?php // var_dump($issue) ?>
            <div class="issue clearfix type-<?php print $issue->getIssueType()['id'] ?>">
                <img class="assignee pull-right img-thumbnail" src="<?php print $issue->getAssignee()['avatarUrls']['48x48'] ?>" alt="<?php print $issue->getAssignee()['displayName'] ?>" />
                <h3 class="number"><?php print $issue->getKey() ?></h3>
                <h2>
                    <img src="<?php print $issue->getIssueType()['iconUrl'] ?>" alt="<?php print $issue->getIssueType()['name'] ?>"/>
                    <?php print '&nbsp;' . $issue->getSummary() ?>
                </h2>
                <?php if (null !== $issue->get('Epic Link')) : ?>
                    <p class="epic btn btn-primary btn-xs"><?php print $epics[$issue->get('Epic Link')] ?></p>
                <?php endif // epic link ?>
                <?php if ($issue->get('Story Points')) : ?>
                    <strong class="estimation pull-right btn-sm btn-default active"><?php print $issue->get('Story Points') ?></strong>
                <?php endif // story points ?>
                <p class="labels"><?php print implode('', array_map(function($i) {return '<span class="btn btn-info btn-xs">' . $i . '</span>'; }, $issue->getLabels())) ?></p>
            </div>
        <?php endforeach // $colTickets ?>

        </div>
    <?php endforeach // $tickets ?>
    </div>
    </div>

</body>
</html>
