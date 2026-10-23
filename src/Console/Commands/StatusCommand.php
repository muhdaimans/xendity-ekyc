<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Console\Commands;

use Illuminate\Console\Command;
use XendityEkyc\XendityEkyc\Enums\StatusMode;
use XendityEkyc\XendityEkyc\Exceptions\XendityEkycException;
use XendityEkyc\XendityEkyc\XendityEkyc;

class StatusCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'xendity-ekyc:status
                            {reference : The stored ref_id or onboarding_id}
                            {--onboarding-id= : Onboarding ID, when querying a transaction that is not stored}';

    /**
     * The command description.
     */
    protected $description = 'Fetch the latest Xendity eKYC status for a transaction';

    /**
     * Execute the console command.
     */
    public function handle(XendityEkyc $ekyc): int
    {
        $reference = $this->argument('reference');

        if (! is_string($reference)) {
            return self::INVALID;
        }
        $onboardingId = $this->option('onboarding-id');

        try {
            $transaction = XendityEkyc::transactions()
                ->where('ref_id', $reference)
                ->orWhere('onboarding_id', $reference)
                ->first();

            if ($transaction !== null) {
                $status = $ekyc->refresh($transaction);
            } elseif (is_string($onboardingId) && $onboardingId !== '') {
                $status = $ekyc->getStatus($reference, $onboardingId, StatusMode::Summary);
            } else {
                $this->components->error("No stored transaction matches [{$reference}]. Pass --onboarding-id to query the gateway directly.");

                return self::FAILURE;
            }
        } catch (XendityEkycException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Ref ID', $status->refId);
        $this->components->twoColumnDetail('Onboarding ID', $status->onboardingId);
        $this->components->twoColumnDetail('Status', $status->status->name);
        $this->components->twoColumnDetail('Result', $status->result->name ?? '-');
        $this->components->twoColumnDetail('Message', $status->rejectMessage ?? '-');

        return self::SUCCESS;
    }
}
