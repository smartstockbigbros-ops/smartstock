<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * SensorModel - prototype data layer for SmartStock.
 *
 * Readings, alerts and sensor status are simulated and kept in the PHP
 * session, so the prototype runs without any database. Swap the private
 * load()/save() methods for real queries when wiring up actual sensors.
 */
class SensorModel extends Model
{
    const TEMP_THRESHOLD     = 30.0; // deg C
    const HUMIDITY_THRESHOLD = 80;   // % RH
    const INTERVAL_MIN       = 5;    // minutes between readings
    const SESSION_KEY        = 'smartstock';

    /** Metal hardware in stock; "offset" = how sensitive the item is to the environment. */
    private $items = [
        ['product' => 'Screws',                 'category' => 'Fasteners', 'offset' =>  0],
        ['product' => 'Iron Nails',             'category' => 'Fasteners', 'offset' =>  1],
        ['product' => 'Steel Bolts',            'category' => 'Fasteners', 'offset' =>  0],
        ['product' => 'Galvanized Pipes',       'category' => 'Plumbing',  'offset' => -1],
        ['product' => 'Stainless Steel Hinges', 'category' => 'Hardware',  'offset' => -2],
    ];

    private $state;

    public function __construct()
    {
        parent::__construct();
        $this->call->library('session');
        $this->state = $this->session->userdata(self::SESSION_KEY);
        if (!is_array($this->state) || empty($this->state['readings'])) {
            $this->state = $this->seed();
            $this->save();
        }
    }

    /* ------------------------------------------------------------------
     * Risk logic
     * ---------------------------------------------------------------- */

    public function points($temp, $hum)
    {
        $h = $hum >= 80 ? 3 : ($hum >= 65 ? 2 : ($hum >= 50 ? 1 : 0));
        $t = $temp >= 30 ? 2 : ($temp >= 28 ? 1 : 0);
        return $h + $t;
    }

    public function level($points)
    {
        if ($points >= 5) return 'Critical';
        if ($points >= 3) return 'High';
        if ($points >= 2) return 'Moderate';
        return 'Low';
    }

    public function reading_status($r)
    {
        $p = $this->points($r['temp'], $r['hum']);
        if ($r['temp'] >= self::TEMP_THRESHOLD || $r['hum'] >= self::HUMIDITY_THRESHOLD) return 'Critical';
        return $p >= 3 ? 'Elevated' : 'Normal';
    }

    /* ------------------------------------------------------------------
     * Reads
     * ---------------------------------------------------------------- */

    public function readings($limit = null)
    {
        $r = $this->state['readings'];
        return $limit ? array_slice($r, -$limit) : $r;
    }

    public function latest()
    {
        return end($this->state['readings']);
    }

    public function recent_readings($limit = 3)
    {
        $out = array_reverse($this->readings($limit));
        foreach ($out as &$r) {
            $r['status'] = $this->reading_status($r);
        }
        return $out;
    }

    public function average_humidity($n = 12)
    {
        $r = $this->readings($n);
        return round(array_sum(array_column($r, 'hum')) / max(1, count($r)));
    }

    public function sensor_online()
    {
        return !empty($this->state['online']);
    }

    /** Minutes the environment has continuously been at Moderate risk or worse. */
    public function exposure_minutes()
    {
        $count = 0;
        foreach (array_reverse($this->state['readings']) as $r) {
            if ($this->points($r['temp'], $r['hum']) < 2) break;
            $count++;
        }
        return $count * self::INTERVAL_MIN;
    }

    public function format_duration($min)
    {
        if ($min < 60) return $min . 'm';
        return floor($min / 60) . 'h ' . str_pad($min % 60, 2, '0', STR_PAD_LEFT) . 'm';
    }

    public function current_risk()
    {
        $l = $this->latest();
        return $this->level($this->points($l['temp'], $l['hum']));
    }

    public function condition_message()
    {
        $l = $this->latest();
        $hot  = $l['temp'] >= 28;
        $humid = $l['hum'] >= 65;
        if ($humid && $hot) return 'High humidity and elevated temperature detected. Prolonged exposure may increase the risk of corrosion for metal hardware.';
        if ($humid)         return 'High humidity detected. Moisture in the air can speed up rust on unprotected metal hardware.';
        if ($hot)           return 'Elevated temperature detected. Heat combined with rising humidity can accelerate corrosion.';
        return 'Storage conditions are within the safe range. No corrosion risk action needed.';
    }

    /** Items with a computed risk level, most-affected first. */
    public function items()
    {
        $l = $this->latest();
        $base = $this->points($l['temp'], $l['hum']);
        $out = [];
        foreach ($this->items as $it) {
            $p = max(0, $base + $it['offset']);
            $out[] = $it + ['points' => $p, 'risk' => $this->level($p), 'status' => 'On stock'];
        }
        usort($out, function ($a, $b) { return $b['points'] <=> $a['points']; });
        return $out;
    }

    public function risk_counts()
    {
        $c = ['Low' => 0, 'Moderate' => 0, 'High' => 0, 'Critical' => 0];
        foreach ($this->items() as $it) $c[$it['risk']]++;
        return $c;
    }

    public function alerts()
    {
        return array_reverse($this->state['alerts']);
    }

    public function unread_count()
    {
        return count(array_filter($this->state['alerts'], function ($a) { return !$a['read']; }));
    }

    /* ------------------------------------------------------------------
     * Writes
     * ---------------------------------------------------------------- */

    /** "Start Sensors Manually": bring sensor online and take a fresh simulated reading. */
    public function start_sensor()
    {
        if (!$this->state['online']) {
            $this->state['online'] = true;
            $this->push_alert('success', 'Sensor Online', 'Sensor Successfully Turned On');
        }

        $prev = $this->latest();
        $temp = round(max(22, min(38, $prev['temp'] + mt_rand(-8, 14) / 10)), 1);
        $hum  = (int) max(35, min(95, $prev['hum'] + mt_rand(-5, 9)));
        $reading = ['ts' => time(), 'temp' => $temp, 'hum' => $hum];
        $this->state['readings'][] = $reading;
        $this->state['readings'] = array_slice($this->state['readings'], -200);

        if ($hum >= self::HUMIDITY_THRESHOLD) {
            $this->push_alert('warning', 'High Humidity', "Sensor Reached {$hum}% RH (Threshold " . self::HUMIDITY_THRESHOLD . '%).');
        }
        if ($temp >= self::TEMP_THRESHOLD) {
            $this->push_alert('warning', 'High Temperature', "Sensor Reached {$temp}C (Threshold " . number_format(self::TEMP_THRESHOLD, 1) . ' C).');
        }
        $this->save();
        return $reading;
    }

    public function stop_sensor()
    {
        if ($this->state['online']) {
            $this->state['online'] = false;
            $this->push_alert('danger', 'Sensor Offline', 'Sensor Disconnected', false);
            $this->save();
        }
    }

    public function mark_all_read()
    {
        foreach ($this->state['alerts'] as &$a) $a['read'] = true;
        $this->save();
    }

    public function reset()
    {
        $this->state = $this->seed();
        $this->save();
    }

    /* ------------------------------------------------------------------
     * Internals
     * ---------------------------------------------------------------- */

    private function push_alert($type, $title, $message, $unread = true)
    {
        $this->state['alerts'][] = [
            'id' => count($this->state['alerts']) + 1,
            'type' => $type, 'title' => $title, 'message' => $message,
            'ts' => time(), 'read' => !$unread,
        ];
    }

    private function save()
    {
        $this->session->set_userdata(self::SESSION_KEY, $this->state);
    }

    /** Seed ~3h of history ending at a "High" risk reading (28.7 C / 67% RH). */
    private function seed()
    {
        $now = time();
        $n = 40;
        $readings = [];
        mt_srand(42);
        for ($i = 0; $i < $n; $i++) {
            $ts = $now - ($n - 1 - $i) * self::INTERVAL_MIN * 60;
            $last = ($i === $n - 1);
            $early = $i < 6;                       // a few drier readings at the start
            $hum  = $early ? mt_rand(58, 62) : (int) round(67 + 2.5 * sin($i / 4 + 1) + mt_rand(-10, 10) / 10);
            $hum  = $early ? $hum : max(65, min(70, $hum));
            $temp = round(28.2 + 1.1 * sin($i / 5) + ($i / $n) * 0.3 + mt_rand(-2, 2) / 10, 1);
            $temp = max(27.4, min(29.8, $temp));
            if ($last) { $hum = 67; $temp = 28.7; }
            $readings[] = ['ts' => $ts, 'temp' => $temp, 'hum' => $hum];
        }
        mt_srand();

        $alerts = [
            ['id' => 1, 'type' => 'success', 'title' => 'Sensor Online',     'message' => 'Sensor Successfully Turned On',          'ts' => $now - 900, 'read' => true],
            ['id' => 2, 'type' => 'danger',  'title' => 'Sensor Offline',    'message' => 'Sensor Disconnected',                    'ts' => $now - 600, 'read' => true],
            ['id' => 3, 'type' => 'warning', 'title' => 'High Temperature',  'message' => 'Sensor Reached 35.4C (Threshold 30.0 C).', 'ts' => $now - 300, 'read' => false],
            ['id' => 4, 'type' => 'warning', 'title' => 'High Humidity',     'message' => 'Sensor Reached 85% RH (Threshold 80%).',   'ts' => $now - 240, 'read' => false],
        ];

        return ['online' => true, 'readings' => $readings, 'alerts' => $alerts];
    }
}
