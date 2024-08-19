<?php

namespace Tests\Feature\Livewire;

use App\Filament\Resources\NewsResource\Pages\CreateNews;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateNewsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Explaination:
     * 
     * Whenever a \Filament\Forms\Concerns\InteractsWithForms is initialized, the initial state of "oldFormState" is null.
     * 
     * When Livewire does update a property, the "updating" event will:
     * - trigger "updatingInteractsWithForms" trait hook & write current to "oldFormState"
     * - update the property.
     * 
     * However, when Livewire does update multiple properties at once, it will effectively repeat mentioned process for each property.
     * This will effectively overwrite "oldFormState" with the updated property.
     * 
     * Keep in mind: All of this is in the "updating" phase / context, so we are interested in the "oldFormState" before the update is applied.
     *
     * In below example, the properties "data.title" and "data.content" are modified:
     * - On updating the first property, "data.title", the "oldFormState" is set from NULL to the correct old state.
     * - On updating the second property, "data.content" the "oldFormState" is set from the correct old state to >the current data with the previous update applied<.
     * - Value of $old in afterStateUpdated on the "title" component is wrong ("Hello World" instead of expected "Hello").
     * 
     * Proposal: Only set the "oldFormState" once during "updating" so it is set to the current data (current = old during updating).
     * 
     * @test
     */
    public function updates_slug_as_expected()
    {
        /* Create the User */
        /** @var User */
        $user = User::factory()->create();
        
        /* "Authenticate" */
        $this->actingAs($user);
        $livewire = Livewire::actingAs($user);

        /* Initialize the CreateNews Filament page */
        $page = $livewire->test(CreateNews::class);


        /**
         * Step 1
         */
        /* Update the title */
        $page->set('data.title', 'Hello');

        /* Ensure "updated" hook updated the slug */
        $page->assertSet('data.slug', 'hello');

        /**
         * Step 2
         */
        /* Update multiple properties at once, i.e. lazy (Builder is lazy) */
        $page
            ->update(updates: [
                /**
                 * Update first property.
                 * This will set "oldFormState" from NULL to "Hello"
                 */
                'data.title' => 'Hello World',
                /**
                 * Update second property.
                 * This will set "oldFormState" from "Hello" to "Hello World" - this is wrong.
                 */
                'data.content' => [
                    [
                        'data' => [
                            'content' => 'LEEEROY JENKINS!'
                        ],
                        'type' => 'paragraph'
                    ]
                ]
            ]);

        /* Check if slug did update as expected (we did not modify it) */
        $page->assertSet('data.slug', 'hello-world');
    }
}
