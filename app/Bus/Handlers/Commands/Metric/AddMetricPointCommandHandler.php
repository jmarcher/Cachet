<?php

/*
 * This file is part of Cachet.
 *
 * (c) Alt Three Services Limited
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CachetHQ\Cachet\Bus\Handlers\Commands\Metric;

use CachetHQ\Cachet\Bus\Commands\Metric\AddMetricPointCommand;
use CachetHQ\Cachet\Bus\Events\Metric\MetricPointWasAddedEvent;
use CachetHQ\Cachet\Dates\DateFactory;
use CachetHQ\Cachet\Models\MetricPoint;
use Carbon\Carbon;

class AddMetricPointCommandHandler
{
    /**
     * The date factory instance.
     *
     * @var \CachetHQ\Cachet\Dates\DateFactory
     */
    protected $dates;

    /**
     * Create a new add metric point command handler instance.
     *
     * @param \CachetHQ\Cachet\Dates\DateFactory $dates
     *
     * @return void
     */
    public function __construct(DateFactory $dates)
    {
        $this->dates = $dates;
    }

    /**
     * Handle the add metric point command.
     *
     * @param \CachetHQ\Cachet\Bus\Commands\Metric\AddMetricPointCommand $command
     *
     * @return \CachetHQ\Cachet\Models\MetricPoint
     */
    public function handle(AddMetricPointCommand $command)
    {
        $metric = $command->metric;
        $createdAt = $command->created_at;

        // Do we have an existing point with the same value?
        $point = $this->findOrCreatePoint($command);

        $point->increment('counter', 1);

        event(new MetricPointWasAddedEvent($point));

        return $point;
    }

    /**
     * Find or create a metric point.
     *
     * @param \CachetHQ\Cachet\Bus\Commands\Metric\AddMetricPointCommand $command
     *
     * @return \CachetHQ\Cachet\Models\MetricPoint
     */
    protected function findOrCreatePoint(AddMetricPointCommand $command)
    {
        $buffer = Carbon::now()->subMinutes($command->metric->threshold);

        if ($point = MetricPoint::where('metric_id', '=', $command->metric->id)->where('value', '=', $command->value)->where('created_at', '>=', $buffer)->first()) {
            return $point;
        }

        $data = [
            'metric_id' => $command->metric->id,
            'value'     => $command->value,
            'counter'   => 0,
        ];

        if ($command->created_at) {
            $data['created_at'] = $this->dates->create('U', $command->created_at)->format('Y-m-d H:i:s');
        }

        return MetricPoint::create($data);
    }
}
