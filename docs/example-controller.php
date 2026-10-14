<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Vinkius\ConnectLaravel\Adapters\OpenAI;
use Vinkius\ConnectLaravel\Exceptions\OverageException;
use Vinkius\ConnectLaravel\Exceptions\QuotaExceededException;

class AssistantController extends Controller
{
    /**
     * A complete OpenAI chat-completions agent loop wired to the user's
     * connected Vinkius capabilities.
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // 1) Everything this user's ready connectors can do, in OpenAI tool form.
        $tools = $user->vinkiusOpenAITools();

        $openai = $this->openai();

        // 2) First turn: let the model decide which capability to call.
        $response = $openai->chat()->completions()->create([
            'model'    => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => "You can act through the user's connected services."],
                ['role' => 'user', 'content' => $request->input('message')],
            ],
            'tools'    => $tools,
        ]);

        $choice = $response['choices'][0];
        $messages = [
            ['role' => 'user', 'content' => $request->input('message')],
            ['role' => 'assistant', 'content' => $choice['message']['content'] ?? null, 'tool_calls' => $choice['message']['tool_calls'] ?? []],
        ];

        // 3) Dispatch each tool call to Vinkius and collect the results.
        try {
            foreach ($choice['message']['tool_calls'] ?? [] as $call) {
                $result = OpenAI::run($user->vinkiusCapabilities(), [
                    'function' => [
                        'name'      => $call['function']['name'],
                        'arguments' => $call['function']['arguments'],
                    ],
                ]);

                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $call['id'],
                    'content'      => implode("\n", array_column($result['content'], 'text')),
                ];
            }
        } catch (QuotaExceededException $e) {
            return response()->json([
                'blocked'     => true,
                'reason'      => 'quota_exceeded',
                'upgrade_url' => $e->upgradeUrl,
            ], 402);
        } catch (OverageException $e) {
            return response()->json([
                'blocked'     => true,
                'reason'      => 'overage_blocked',
                'upgrade_url' => $e->upgradeUrl,
            ], 402);
        }

        // 4) Feed the results back and get the final answer.
        $final = $openai->chat()->completions()->create([
            'model'    => 'gpt-4o',
            'messages' => $messages,
        ]);

        return response()->json(['reply' => $final['choices'][0]['message']['content']]);
    }

    /**
     * The OpenAI PHP SDK client (any provider-compatible client works).
     *
     * @return \OpenAI
     */
    private function openai()
    {
        return \OpenAI::client(config('services.openai.key'));
    }
}
