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
    'Imperial Bounty' => [
        'imperial_bounty' => ['Imperial Bounty (credits a night)', 'The Crown pays every pilot this much once a night, at daily maintenance. Each pilot is paid at most once a day, however often maintenance runs. 0 turns it off. At most ' . number_format_i18n(IB_Settings::IMPERIAL_BOUNTY_MAX) . '.', IB_Settings::IMPERIAL_BOUNTY_MAX],
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
        'colonists_per_hold' => ['Colonists per cargo hold', 'Settlers travel in berths, so one hold carries this many.'],
        'planet_output_percent' => ['Planet output (%)', 'Scales every planet class\'s daily production, in units of cargo. 100 is the base rate, 500 the default; each bastion level adds a further 10%.'],
        'planet_autosell_percent' => ['Daily auto-sale (% of stock)', 'How much of each colony\'s ferrium ore, biostock and machinery the Crown\'s factors buy every day. 0 turns the sale off and stock simply piles up. Needs a bastion, since the money goes into its Vault. Owners choose, per world, which of the three their factors may buy.'],
        'planet_autosell_price_percent' => ['Auto-sale price (% of base)', 'What the factors pay, against each commodity\'s base price. Below 100 so that hauling cargo to a port by hand still pays better.'],
        'vault_transfer_fee_percent' => ['Remote Vault fee (%)', 'Charged on credits drawn from a planet Vault from elsewhere in the galaxy. 0 for no fee.'],
        'price_survey_drone' => ['Survey drone price', 'Charts every sector within 3 warps of where it is launched.'],
        'price_nebula_chart' => ['Nebula chart price', 'Charts every sector in one nebula.'],
    ],
    'Vending machines' => [
        'vending_per_1000' => ['Vending machines needed per 1,000 fighters', 'A world needs this many vending machines, kept stocked, for every 1,000 fighters in its garrison. Fighters aboard ships and in deployed fleets are not counted. 0 turns vending machines off.'],
        'price_vending' => ['Vending machine price', 'Sold at the Aurelian Armory and the Imperial Drydock.'],
        'vending_restock' => ['Restock (credits a day per machine)', 'Paid at daily maintenance for each machine in use, from the world\'s supply fund (and its Vault, if the setting below allows).'],
        'vending_bastion_percent' => ['Restock rise per bastion level (%)', 'Each bastion level adds this much to a world\'s restock, so bigger strongholds cost more to keep. At most 100.'],
        'vending_vault_pays' => ['Vault pays the restock', '1 = when the supply fund runs dry the Vault pays, 0 = only the supply fund pays, so owners have to keep it topped up.'],
        'vending_warn_days' => ['Warn when the supply fund runs low (days)', 'Owners get a message when a world\'s supply fund drops below this many days of restocking. 0 = no warning.'],
        'vending_grace_days' => ['Vending grace (days)', 'Days a world can go short of stocked machines before fighters start to desert. A short world raises no new fighters meanwhile.'],
        'vending_desert_percent' => ['Fighters deserting a day (%)', 'Once the grace runs out, this share of the unfed fighters deserts every day until it is fixed. At most 50. A world left with no fighters can be claimed.'],
    ],
    'Smugglers' => [
        'smuggler_ports_percent' => ['Smugglers\' dens (% of frontier ports)', 'Share of trading ports outside the Imperial Core that are dens, where goods are stolen instead of bought. Applied at the next Universe Forge (an existing universe was given its dens once, on update). At most 50.'],
        'smuggler_discount_percent' => ['Den discount (% under market)', 'How far under the market price a den\'s goods cost to steal. Never less than 50, at most 90. No haggling at a den.'],
        'smuggler_haul_percent' => ['Haul per visit (% of holds)', 'How much a pilot can carry off from one docking at a den, as a share of their cargo holds. Docking again costs a turn and risks customs again.'],
        'smuggler_catch_percent' => ['Customs catch chance (%)', 'Chance Imperial customs catches a pilot docking at a den. Caught pilots are fined and turned away. At most 80.'],
        'smuggler_free_visits' => ['Free dockings a day', 'Dockings at the same den in 24 hours before the catch chance starts to climb.'],
        'smuggler_repeat_step' => ['Catch rise per extra docking (%)', 'Added to the catch chance for each docking at the same den past the free ones. The total never passes 80%.'],
        'smuggler_fine_percent' => ['Customs fine (% of credits carried)', 'Taken from a caught pilot\'s credits, at least 25 credits. At most 50.'],
        'strongbox_seed' => ['Strongbox seed (credits)', 'The Crown puts this much into the Fines Fund when a game starts and again each time a pilot finds the strongbox, so there is always something to hunt for. Customs fines add to it.'],
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
                               type="<?php echo $text ? 'text' : 'number'; ?>" <?php echo $text ? '' : 'min="0" step="1"'; ?>
                               <?php if (!$text && isset($row[2])) : ?>max="<?php echo esc_attr((int) $row[2]); ?>"<?php endif; ?>
                               value="<?php echo esc_attr($s[$key]); ?>" class="<?php echo $text ? 'regular-text' : 'small-text'; ?>">
                        <?php if ($row[1]) : ?><p class="description"><?php echo esc_html($row[1]); ?></p><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>
    <?php submit_button('Save settings'); ?>
</form>
