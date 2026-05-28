<!DOCTYPE html>
<html>
<head>
    <?= $this->Html->charset() ?>
    <title><?= $this->fetch('title') ?> | SkillSprint</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Include common styles -->
    <?= $this->Html->css('main') ?>
</head>
<body>
<div id="root"></div>

<?php
$session = $this->request->getSession();
$isLoggedIn = $session->read('Auth.User');

// Pass login state to React
echo '<script>window.isLoggedIn = ' . ($isLoggedIn ? 'true' : 'false') . ';</script>';

// Load the correct React bundle
if ($isLoggedIn) {
    echo $this->Html->script('dashboard'); // index_old.js bundle
} else {
    echo $this->Html->script('app'); // App_old.js bundle
}
?>
</body>
</html>
