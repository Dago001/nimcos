<?php

namespace App\Services\Voting;

use App\Services\Voting\Exceptions\VotingException;

/**
 * Server-side ballot validation (spec §13). JavaScript checks are a convenience only.
 */
class BallotValidator
{
    /**
     * Normalise raw input into [election_position_id => list<candidate_id>] and validate it.
     *
     * @param  mixed  $raw  expected: array<position_id, candidate_id|list<candidate_id>>
     * @param  bool  $requireComplete  false while the voter is still editing
     * @return array<string, list<string>>
     *
     * @throws VotingException
     */
    public function validate(BallotDefinition $ballot, mixed $raw, bool $requireComplete = true): array
    {
        $raw = is_array($raw) ? $raw : [];
        $errors = [];
        $selections = [];
        $tampered = false;

        foreach ($raw as $positionId => $value) {
            if (! is_string($positionId) || $ballot->position($positionId) === null) {
                $tampered = true;

                continue;
            }
        }

        foreach ($ballot->positions as $position) {
            $value = $raw[$position->id] ?? [];
            $ids = is_array($value) ? array_values($value) : [$value];
            $ids = array_values(array_filter($ids, fn ($v) => is_string($v) && $v !== ''));
            $name = $position->position->name;

            if (count($ids) !== count(array_unique($ids))) {
                $errors[$position->id] = "The same candidate was selected more than once for {$name}.";
                $tampered = true;

                continue;
            }

            foreach ($ids as $candidateId) {
                if ($ballot->candidate($position->id, $candidateId) === null) {
                    // Candidate not active, not in this election, or not in this position.
                    $errors[$position->id] = "An invalid selection was made for {$name}. Please choose again.";
                    $tampered = true;

                    continue 2;
                }
            }

            if (count($ids) > $position->seats) {
                $errors[$position->id] = "You may select at most {$position->seats} candidate(s) for {$name}.";

                continue;
            }

            if ($requireComplete && $position->is_required && count($ids) === 0) {
                $errors[$position->id] = "Please make a selection for {$name}.";

                continue;
            }

            $selections[$position->id] = $ids;
        }

        if ($errors !== [] || $tampered) {
            $exception = VotingException::invalidBallot($errors ?: ['_ballot' => 'Your ballot contained an invalid entry. Please review your selections.']);
            if ($tampered) {
                throw new TamperedBallotException($exception);
            }
            throw $exception;
        }

        return $selections;
    }
}
