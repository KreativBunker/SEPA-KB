<?php
/** @var string $csrf */
/** @var string|null $error */
/** @var string $currentCommit */
/** @var string $branch */
/** @var string $remoteUrl */
/** @var array $pendingCommits */
?>

<div class="card">
    <h1>System Update</h1>

    <?php if ($error): ?>
        <div class="flash error"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <p><strong>Aktuelle Version:</strong> <code class="mono"><?php echo htmlspecialchars($currentCommit); ?></code></p>
        <p><strong>Branch:</strong> <code class="mono"><?php echo htmlspecialchars($branch); ?></code></p>
        <p><strong>Remote:</strong> <code class="mono"><?php echo htmlspecialchars($remoteUrl); ?></code></p>

        <?php if (!empty($pendingCommits)): ?>
            <h2>Verfuegbare Updates (<?php echo count($pendingCommits); ?>)</h2>
            <pre class="mono" style="background:#f3f5fb; padding:12px; border-radius:8px; overflow-x:auto; font-size:13px;"><?php
                foreach ($pendingCommits as $commit) {
                    echo htmlspecialchars($commit) . "\n";
                }
            ?></pre>
            <form method="post" action="<?php echo \App\Support\App::url('/update'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <button type="submit" class="btn" onclick="this.disabled=true; this.innerText='Update laeuft...'; this.form.submit();">Update ausfuehren</button>
            </form>
        <?php else: ?>
            <p class="muted">Keine Updates verfuegbar. Das System ist aktuell.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
