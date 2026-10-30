<?php

namespace Tests\Learner\Feature\ExamTakingAndSubmission;

use Tests\Learner\TestCase;

class ExamAttemptFlowTest extends TestCase
{
    public function test_guest_must_login_before_starting_exam(): void
    {
        $this->get('/exams')->assertRedirect('/login');
    }
}
