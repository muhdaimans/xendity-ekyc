<?php

declare(strict_types=1);

namespace XendityEkyc\XendityEkyc\Console\Commands;

use Illuminate\Console\Command;
use XendityEkyc\XendityEkyc\Data\StatusResult;
use XendityEkyc\XendityEkyc\Enums\TransactionResult;
use XendityEkyc\XendityEkyc\Enums\TransactionStatus;
use XendityEkyc\XendityEkyc\Events\TransactionUpdated;
use XendityEkyc\XendityEkyc\XendityEkyc;

class SimulateCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'xendity-ekyc:simulate
                            {reference : The stored ref_id or onboarding_id}
                            {outcome : success or failed}
                            {--message= : Reject message to store with the result}';

    /**
     * The command description.
     */
    protected $description = 'Mark a stored Xendity eKYC transaction as successful or failed without calling the gateway';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->components->error('Transactions cannot be simulated in production.');

            return self::FAILURE;
        }

        $reference = $this->input->getArgument('reference');
        $outcome = $this->input->getArgument('outcome');

        if (! is_string($reference) || ! is_string($outcome)) {
            return self::INVALID;
        }

        $result = match (strtolower($outcome)) {
            'success' => TransactionResult::Approved,
            'failed' => TransactionResult::Rejected,
            default => null,
        };

        if ($result === null) {
            $this->components->error("Unknown outcome [{$outcome}]. Use success or failed.");

            return self::INVALID;
        }

        $transaction = XendityEkyc::transactions()
            ->where('ref_id', $reference)
            ->orWhere('onboarding_id', $reference)
            ->first();

        if ($transaction === null) {
            $this->components->error("No stored transaction matches [{$reference}].");

            return self::FAILURE;
        }

        $message = $this->input->getOption('message');
        $message = is_string($message) && $message !== ''
            ? $message
            : ($result === TransactionResult::Approved ? 'EKYC onboarding process successful' : 'EKYC onboarding process failed');

        $status = StatusResult::fromArray([
            'ref_id' => $transaction->ref_id,
            'onboarding_id' => $transaction->onboarding_id,
            'status' => TransactionStatus::Completed->value,
            'result' => $result->value,
            'reject_message' => $message,
            'simulated' => true,
        ]);

        if ($transaction->applyStatus($status)) {
            TransactionUpdated::dispatch($transaction, $status);
        }

        $this->components->twoColumnDetail('Ref ID', $status->refId);
        $this->components->twoColumnDetail('Onboarding ID', $status->onboardingId);
        $this->components->twoColumnDetail('Status', $status->status->name);
        $this->components->twoColumnDetail('Result', $result->name);
        $this->components->twoColumnDetail('Message', $message);

        return self::SUCCESS;
    }
}
