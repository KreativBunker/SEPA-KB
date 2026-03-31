<?php
/** @var string $csrf */
/** @var string|null $error */
/** @var array|null $currentVersion */
/** @var array|null $latestVersion */
/** @var string $repoUrl */
/** @var string $branch */
/** @var bool $updateAvailable */
?>

<div class="card">
    <h1>System Update</h1>

    <?php if ($error): ?>
        <div class="flash error"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <table style="max-width:500px; margin-bottom:18px;">
            <tr>
                <td><strong>Repository</strong></td>
                <td class="mono"><?php echo htmlspecialchars($repoUrl); ?></td>
            </tr>
            <tr>
                <td><strong>Branch</strong></td>
                <td class="mono"><?php echo htmlspecialchars($branch); ?></td>
            </tr>
            <tr>
                <td><strong>Installierte Version</strong></td>
                <td class="mono">
                    <?php if ($currentVersion): ?>
                        <?php echo htmlspecialchars(substr($currentVersion['sha'], 0, 7)); ?>
                        <span class="muted">&mdash; <?php echo htmlspecialchars(strtok($currentVersion['message'], "\n")); ?></span>
                        <br><span class="muted"><?php echo htmlspecialchars($currentVersion['updated_at'] ?? ''); ?></span>
                    <?php else: ?>
                        <span class="muted">Unbekannt (noch kein Update ausgefuehrt)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Neueste Version</strong></td>
                <td class="mono">
                    <?php if ($latestVersion): ?>
                        <?php echo htmlspecialchars(substr($latestVersion['sha'], 0, 7)); ?>
                        <span class="muted">&mdash; <?php echo htmlspecialchars(strtok($latestVersion['message'], "\n")); ?></span>
                    <?php else: ?>
                        <span class="muted">Konnte nicht abgerufen werden</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php if ($updateAvailable): ?>
            <form method="post" action="<?php echo \App\Support\App::url('/update'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <button type="submit" class="btn" onclick="this.disabled=true; this.innerText='Update wird heruntergeladen...'; this.form.submit();">Update herunterladen &amp; installieren</button>
            </form>
        <?php else: ?>
            <?php if ($latestVersion): ?>
                <p><span class="pill ok">Aktuell</span> Das System ist auf dem neuesten Stand.</p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
