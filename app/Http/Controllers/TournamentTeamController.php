<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TournamentTeamController extends Controller
{
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $validated = $request->validate([
            'team_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('teams', 'name')->where('tournament_id', $tournament->id),
            ],
            'team_group_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('tournament_groups', 'id')->where('tournament_id', $tournament->id),
            ],
        ], [
            'team_name.required' => 'Vul een teamnaam in.',
            'team_name.max' => 'De teamnaam mag maximaal 255 tekens bevatten.',
            'team_name.unique' => 'Dit team staat al bij dit toernooi.',
            'team_group_id.exists' => 'Kies een poule van dit toernooi.',
        ]);

        // Keep the number of registered teams within the tournament's configured total.
        if ($tournament->teams()->count() >= $tournament->team_count) {
            return back()
                ->withErrors(['team_name' => 'Het ingestelde aantal teams is al bereikt.'])
                ->withInput();
        }

        $tournament->teams()->create([
            'name' => $validated['team_name'],
            'tournament_group_id' => $validated['team_group_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Team toegevoegd.');
    }

    public function update(Request $request, Tournament $tournament, Team $team): RedirectResponse
    {
        // Keep validation errors tied to the team row being edited.
        $field = 'team_name_'.$team->id;
        $groupField = 'team_group_id_'.$team->id;

        // A team can only be placed in a group belonging to its tournament.
        $validated = $request->validate([
            $field => [
                'required',
                'string',
                'max:255',
                Rule::unique('teams', 'name')
                    ->where('tournament_id', $tournament->id)
                    ->ignore($team->id),
            ],
            $groupField => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('tournament_groups', 'id')->where('tournament_id', $tournament->id),
            ],
        ], [
            $field.'.required' => 'Vul een teamnaam in.',
            $field.'.max' => 'De teamnaam mag maximaal 255 tekens bevatten.',
            $field.'.unique' => 'Dit team staat al bij dit toernooi.',
            $groupField.'.exists' => 'Kies een poule van dit toernooi.',
        ]);

        $teamData = [
            'name' => $validated[$field],
        ];

        if (array_key_exists($groupField, $validated)) {
            $teamData['tournament_group_id'] = $validated[$groupField];
        }

        $team->update($teamData);

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Team bijgewerkt.');
    }

    public function destroy(Tournament $tournament, Team $team): RedirectResponse
    {
        if ($team->homeMatches()->exists() || $team->awayMatches()->exists()) {
            return back()->with('error', 'Dit team kan niet worden verwijderd zolang het aan een wedstrijd is gekoppeld.');
        }

        $team->delete();

        return redirect()
            ->route('admin.tournaments.edit', $tournament)
            ->with('success', 'Team verwijderd.');
    }
}
