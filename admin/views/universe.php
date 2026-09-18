<?php
if (!defined('ABSPATH')) exit;
$universe = get_option('ib_universe');
$d = IB_Universe_Forge::defaults();
$validate = !empty($_GET['validate']);
?>
<?php if ($universe) : ?>
    <div class="ib-box">
        <h2>Current universe</h2>
        <table class="widefat striped" style="max-width:600px">
            <tr><th>Generated</th><td><?php echo esc_html($universe['generated_at']); ?></td></tr>
            <tr><th>Sectors</th><td><?php echo IB_Game::fmt($universe['sectors']); ?> (Imperial Core 1-<?php echo (int) $universe['core']; ?>)</td></tr>
            <tr><th>Warps</th><td><?php echo IB_Game::fmt($universe['warps']); ?> (<?php echo (int) $universe['one_way']; ?> one-way lanes)</td></tr>
            <tr><th>Ports</th><td><?php echo IB_Game::fmt($universe['ports']); ?></td></tr>
            <tr><th>Planets</th><td><?php echo IB_Game::fmt($universe['planets']); ?></td></tr>
            <tr><th>Imperial Drydock</th><td>Sector <?php echo (int) $universe['drydock']; ?></td></tr>
            <tr><th>Vraxori home</th><td>Sector <?php echo (int) $universe['vraxori_home']; ?></td></tr>
            <tr><th>Seed</th><td><?php echo (int) $universe['seed']; ?></td></tr>
        </table>
        <p>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ib_universe&validate=1')); ?>">Validate universe</a>
            <?php echo IB_Admin::form_open('export', 'style="display:inline"'); ?>
                <?php submit_button('Export universe (JSON)', 'secondary', 'submit', false); ?>
            </form>
        </p>
        <?php if ($validate) : ?>
            <h3>Validation report</h3>
            <ul>
                <?php foreach (IB_Universe_Forge::validate() as $row) : ?>
                    <li class="ib-<?php echo esc_attr($row[0]); ?>"><strong><?php echo esc_html(strtoupper($row[0])); ?></strong> &mdash; <?php echo esc_html($row[1]); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="ib-box">
    <h2>Generate universe (Universe Forge)</h2>
    <p>Creates a new random galaxy: sectors laid out in space and linked by 1-6 warp lanes, the Aurelian Armory and the Crown World Aurelia Prime in sector 1,
        the Imperial Core around it, trading ports of classes 1-8, the Imperial Drydock, unclaimed planets and alien faction fleets.</p>
    <div class="ib-danger">
        <strong>Warning:</strong> the Universe Forge permanently erases the existing universe <em>and all player data</em>: pilots, teams, planets, messages and news.
        Pilots must create new characters afterwards.
    </div>
    <?php echo IB_Admin::form_open('forge'); ?>
        <table class="form-table" role="presentation">
            <tr><th><label for="ib-sectors">Sectors</label></th>
                <td><input id="ib-sectors" type="number" name="sectors" value="<?php echo (int) $d['sectors']; ?>" min="<?php echo IB_Universe_Forge::MIN_SECTORS; ?>" max="<?php echo IB_Universe_Forge::MAX_SECTORS; ?>" class="small-text">
                    <p class="description"><?php echo IB_Universe_Forge::MIN_SECTORS; ?>-<?php echo IB_Universe_Forge::MAX_SECTORS; ?>. The default 500 suits 10 turns a day; go larger if you raise turns per day.</p></td></tr>
            <tr><th><label for="ib-pd">Port density %</label></th>
                <td><input id="ib-pd" type="number" name="port_density" value="<?php echo (int) $d['port_density']; ?>" min="5" max="90" class="small-text">
                    <p class="description">Share of sectors with a trading port.</p></td></tr>
            <tr><th><label for="ib-pl">Planet density %</label></th>
                <td><input id="ib-pl" type="number" name="planet_density" value="<?php echo (int) $d['planet_density']; ?>" min="0" max="50" class="small-text">
                    <p class="description">Share of sectors with an unclaimed planet.</p></td></tr>
            <tr><th><label for="ib-fs">Alien strength %</label></th>
                <td><input id="ib-fs" type="number" name="faction_strength" value="<?php echo (int) $d['faction_strength']; ?>" min="0" max="500" class="small-text">
                    <p class="description">Scales how many alien fleets are seeded. 0 disables most of them.</p></td></tr>
            <tr><th><label for="ib-ow">One-way lanes %</label></th>
                <td><input id="ib-ow" type="number" name="oneway_percent" value="<?php echo (int) $d['oneway_percent']; ?>" min="0" max="20" class="small-text">
                    <p class="description">Lanes made one-way. Every sector always keeps a route to and from Aurelia.</p></td></tr>
            <tr><th><label for="ib-seed">Random seed</label></th>
                <td><input id="ib-seed" type="number" name="seed" value="" min="0" class="regular-text" placeholder="blank = random">
                    <p class="description">Reuse a seed to regenerate an identical universe.</p></td></tr>
            <tr><th>Confirm</th>
                <td><label><input type="checkbox" name="confirm_warning" value="1" required> I understand this erases the current universe and every player.</label><br><br>
                    <label>Type <code>FORGE</code> to confirm: <input type="text" name="confirm_text" required autocomplete="off"></label></td></tr>
        </table>
        <?php submit_button('Forge the universe', 'primary'); ?>
    </form>
</div>

<?php if ($universe) : ?>
<div class="ib-box">
    <h2>Reset universe</h2>
    <p>Erases the universe and all player data without generating a new one, taking the game offline until the next Universe Forge.</p>
    <?php echo IB_Admin::form_open('reset_universe'); ?>
        <p><label><input type="checkbox" name="confirm_warning" value="1" required> I understand this erases everything.</label></p>
        <p><label>Type <code>RESET</code> to confirm: <input type="text" name="confirm_text" required autocomplete="off"></label></p>
        <?php submit_button('Reset universe', 'delete', 'submit', false); ?>
    </form>
</div>
<?php endif; ?>
