<?php
if (!defined('ABSPATH')) exit;
$s = IB_Settings::all();
$fields = [
    'General' => [
        'allow_new_players' => ['Allow new pilots', '1 = open registration, 0 = closed.'],
        'turns_per_day' => ['Turns per day', 'Turns reset at midnight (site timezone). Unused turns do not carry over.'],
        'team_max_members' => ['Max team size', '0 = unlimited.'],
        'news_retention_days' => ['Keep news for (days)', ''],
    ],
    'Turn costs' => [
        'move_turn_cost' => ['Warp to adjacent sector', ''],
        'dock_turn_cost' => ['Dock at a port', 'Trading while docked is free.'],
        'attack_turn_cost' => ['Attack', ''],
    ],
    'New pilots' => [
        'starting_credits' => ['Starting credits', ''],
        'starting_fighters' => ['Starting fighters', ''],
        'starting_shields' => ['Starting shields', ''],
        'starting_holds' => ['Starting cargo holds', ''],
    ],
    'Economy' => [
        'port_regen_percent' => ['Port regeneration % per hour', 'How fast ports restock and regain demand.'],
        'max_haggle_attempts' => ['Haggle attempts', 'Failed counter-offers allowed before a port stops haggling for an hour.'],
        'price_fighter' => ['Fighter price', ''],
        'price_shield' => ['Shield point price', ''],
        'price_hold_base' => ['Cargo hold base price', 'Each additional hold costs 5 credits more than the last.'],
        'price_worldseed' => ['Worldseed price', ''],
        'price_colonist' => ['Colonist price (Aurelia Prime)', ''],
        'price_survey_drone' => ['Survey drone price', 'Charts every sector within 3 warps of where it is launched.'],
        'price_nebula_chart' => ['Nebula chart price', 'Charts every sector in one nebula.'],
    ],
    'Exploration' => [
        'discovery_chance' => ['Discovery chance %', 'Chance that a first visit to a sector outside the Imperial Core turns up salvage, a credit cache, abandoned fighters, a survey beacon or a pirate ambush.'],
    ],
    'Universe' => [
        'core_sectors' => ['Imperial Core sectors', 'Sectors 1-N are protected the Imperial Core. Applied at the next Universe Forge.'],
        'vraxori_regeneration_rate' => ['Vraxori fighter regeneration / hour', 'Per fleet, up to ' . IB_Factions::VRAXORI_MAX_FIGHTERS . '.'],
    ],
];
?>
<?php echo IB_Admin::form_open('save_settings'); ?>
    <?php foreach ($fields as $section => $rows) : ?>
        <h2><?php echo esc_html($section); ?></h2>
        <table class="form-table" role="presentation">
            <?php foreach ($rows as $key => $row) :
                $text = in_array($key, IB_Settings::text_keys(), true); ?>
                <tr>
                    <th><label for="ib-<?php echo esc_attr($key); ?>"><?php echo esc_html($row[0]); ?></label></th>
                    <td>
                        <input id="ib-<?php echo esc_attr($key); ?>" name="ib[<?php echo esc_attr($key); ?>]"
                               type="<?php echo $text ? 'text' : 'number'; ?>" <?php echo $text ? '' : 'min="0"'; ?>
                               value="<?php echo esc_attr($s[$key]); ?>" class="<?php echo $text ? 'regular-text' : 'small-text'; ?>">
                        <?php if ($row[1]) : ?><p class="description"><?php echo esc_html($row[1]); ?></p><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>
    <?php submit_button('Save settings'); ?>
</form>
