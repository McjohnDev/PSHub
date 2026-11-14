<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_pshub;

use local_pshub\payment\fake_provider;
use local_pshub\payment\payment_request;
use local_pshub\payment\payment_status;
use local_pshub\payment\provider_factory;

/**
 * The payment seam, and the fake provider that makes it testable.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pshub\payment\fake_provider
 * @covers     \local_pshub\payment\provider_factory
 * @covers     \local_pshub\payment\payment_status
 */
final class fake_provider_test extends \advanced_testcase {

    /**
     * Enable the fake provider the way a developer's config.php would.
     *
     * @return void
     */
    private function enable_fake(): void {
        global $CFG;

        $CFG->pshub_fake_payments = true;
    }

    /**
     * A request for 25 000 XAF by Mobile Money.
     *
     * @param array $overrides
     * @return payment_request
     */
    private function request(array $overrides = []): payment_request {
        return new payment_request(
            $overrides['reference'] ?? 'PSHUB-TEST-1',
            $overrides['amount'] ?? 25000,
            $overrides['currency'] ?? 'XAF',
            $overrides['method'] ?? 'mtn_momo',
            $overrides['msisdn'] ?? '+237699112233'
        );
    }

    /**
     * The fake refuses to exist unless config.php says so.
     *
     * The first of the four guards: it says "paid" for free, so switching it
     * on must be a deliberate deployment, not a setting a restore could carry
     * from one site to another.
     */
    public function test_the_fake_refuses_without_the_config_line(): void {
        global $CFG;
        $this->resetAfterTest();

        unset($CFG->pshub_fake_payments);

        $this->expectException(\moodle_exception::class);
        new fake_provider();
    }

    /**
     * And it refuses rather than quietly handing back the real one.
     *
     * A dev box that silently started charging real cards would be worse than
     * an error on screen.
     */
    public function test_a_site_with_no_provider_says_so(): void {
        global $CFG;
        $this->resetAfterTest();

        unset($CFG->pshub_fake_payments);

        $this->assertFalse(provider_factory::is_fake_enabled());
        $this->assertTrue(provider_factory::is_live());

        $this->expectException(\moodle_exception::class);
        provider_factory::get();
    }

    /**
     * With the line present, the factory hands back the fake.
     */
    public function test_the_factory_returns_the_fake_when_enabled(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $provider = provider_factory::get();

        $this->assertInstanceOf(fake_provider::class, $provider);
        $this->assertSame('fake', $provider->get_name());
        $this->assertFalse(provider_factory::is_live(),
            'the console must be able to tell that this site is not taking real money');
    }

    /**
     * Mobile Money pushes; it does not redirect.
     *
     * The commonest way a MoMo checkout is got wrong is assuming a redirect
     * that never comes (§9.2).
     */
    public function test_mobile_money_pushes_rather_than_redirecting(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $start = (new fake_provider())->start($this->request());

        $this->assertFalse($start->is_redirect());
        $this->assertNotEmpty($start->instructions);
        $this->assertStringContainsString('699112233', $start->instructions);
    }

    /**
     * A card sends the browser somewhere.
     */
    public function test_a_card_redirects(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $start = (new fake_provider())->start($this->request(['method' => 'card']));

        $this->assertTrue($start->is_redirect());
        $this->assertNotEmpty($start->redirecturl);
    }

    /**
     * Starting twice starts one payment.
     *
     * A learner who taps "pay" and then reloads must not be charged twice.
     */
    public function test_starting_twice_is_the_same_payment(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $provider = new fake_provider();
        $first = $provider->start($this->request());
        $second = $provider->start($this->request());

        $this->assertSame($first->providerref, $second->providerref);
    }

    /**
     * A callback is only trusted when it is signed.
     */
    public function test_an_unsigned_callback_is_refused(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $provider = new fake_provider();
        $body = $provider->build_callback('PSHUB-TEST-1', 25000);

        $this->assertFalse($provider->verify_signature($body, []));
        $this->assertFalse($provider->verify_signature($body,
            [fake_provider::SIGNATURE_HEADER => 'not-the-signature']));
        $this->assertTrue($provider->verify_signature($body,
            [fake_provider::SIGNATURE_HEADER => $provider->sign($body)]));
    }

    /**
     * Tampering with a signed body breaks the signature.
     *
     * The whole point: the signature covers the amount, so a body edited in
     * flight is refused.
     */
    public function test_a_tampered_body_fails_its_signature(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $provider = new fake_provider();
        $body = $provider->build_callback('PSHUB-TEST-1', 25000);
        $signature = $provider->sign($body);

        $tampered = str_replace('25000', '1', $body);

        $this->assertFalse($provider->verify_signature($tampered,
            [fake_provider::SIGNATURE_HEADER => $signature]));
    }

    /**
     * The header is matched whatever its case.
     *
     * Web servers normalise header names differently, and a signature check
     * that only works on one of them fails in production and nowhere else.
     */
    public function test_the_signature_header_is_case_insensitive(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $provider = new fake_provider();
        $body = $provider->build_callback('PSHUB-TEST-1', 25000);

        $this->assertTrue($provider->verify_signature($body,
            ['Verif-Hash' => $provider->sign($body)]));
        $this->assertTrue($provider->verify_signature($body,
            ['VERIF-HASH' => $provider->sign($body)]));
    }

    /**
     * A readable callback becomes a status with its amount and currency.
     */
    public function test_a_callback_carries_what_was_paid(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $provider = new fake_provider();
        $status = $provider->parse_callback($provider->build_callback('PSHUB-TEST-1', 25000));

        $this->assertTrue($status->is_paid());
        $this->assertSame('PSHUB-TEST-1', $status->reference);
        $this->assertTrue($status->matches(25000, 'XAF'));
    }

    /**
     * Rubbish is refused rather than guessed at.
     */
    public function test_an_unreadable_callback_throws(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $this->expectException(\moodle_exception::class);
        (new fake_provider())->parse_callback('not json at all');
    }

    /**
     * The wrong amount does not match, however well signed it is.
     *
     * 1 000 XAF against a 25 000 XAF course is not payment for the course
     * (§9.3).
     */
    public function test_the_wrong_amount_does_not_match(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $provider = new fake_provider(fake_provider::SCRIPT_WRONG_AMOUNT);
        $status = $provider->parse_callback($provider->build_callback('PSHUB-TEST-1', 25000));

        $this->assertTrue($status->is_paid(), 'the provider says it paid');
        $this->assertFalse($status->matches(25000, 'XAF'), 'but not for this');
    }

    /**
     * Nor does the right number of the wrong currency.
     */
    public function test_the_wrong_currency_does_not_match(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $provider = new fake_provider(fake_provider::SCRIPT_WRONG_CURRENCY);
        $status = $provider->parse_callback($provider->build_callback('PSHUB-TEST-1', 25000));

        $this->assertSame(25000, $status->amount);
        $this->assertFalse($status->matches(25000, 'XAF'));
    }

    /**
     * A declining provider declines.
     */
    public function test_a_declined_payment_is_not_paid(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $provider = new fake_provider(fake_provider::SCRIPT_DECLINE);

        $this->assertFalse($provider->query('PSHUB-TEST-1')->is_paid());
        $this->assertSame(payment_status::FAILED, $provider->query('PSHUB-TEST-1')->state);
    }

    /**
     * A silent provider stays pending, which is what reconciliation is for.
     *
     * This is the script that proves the pending screen never assumes success
     * and the reconciliation task has something to find (§9.3).
     */
    public function test_a_silent_payment_stays_pending(): void {
        $this->resetAfterTest();
        $this->enable_fake();

        $provider = new fake_provider(fake_provider::SCRIPT_SILENT);
        $start = $provider->start($this->request());

        $this->assertFalse($start->is_redirect());
        $this->assertSame(payment_status::PENDING, $provider->query('PSHUB-TEST-1')->state);
    }
}
