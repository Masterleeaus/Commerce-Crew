<?php

namespace App\Extensions\ChatbotVoice\System\Http\Controllers;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity as EntityFacade;
use App\Extensions\ChatbotVoice\System\Http\Requests\VoiceChatbotStoreRequest;
use App\Extensions\ChatbotVoice\System\Http\Requests\VoiceChatbotUpdateRequest;
use App\Extensions\ChatbotVoice\System\Models\ExtVoiceChatbot;
use App\Extensions\ChatbotVoice\System\Services\ChabotVoiceService;
use App\Helpers\Classes\Helper;
use App\Helpers\Classes\RateLimiter\RateLimiter;
use App\Http\Controllers\Controller;
use App\Services\Ai\ElevenLabsService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ChatbotVoiceController extends Controller
{
    public function __construct(public ChabotVoiceService $service) {}

    // index
    public function index(): View
    {
        $chatbots = $this->service->query()
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')->paginate(perPage: 100);

        return view('chatbot-voice::index', [
            'chatbots' => $chatbots,
            'avatars' 	=> $this->service->avatars(),
            'voices' 	 => $this->service->getVoices(),
        ]);
    }

    // store voice chatbot
    public function store(VoiceChatbotStoreRequest $request): JsonResource|JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $reqData = $request->validated();
        if (isset($reqData['language']) && $reqData['language'] == 'auto') {
            unset($reqData['language']);
        }

        $res = $this->service->createAgent($reqData);

        if ($res->getData()->status === 'success') {
            $reqData['agent_id'] = $res->getData()->resData->agent_id;
            $reqData['voice_id'] = ElevenLabsService::DEFAULT_ELEVENLABS_VOICE_ID;
            $reqData['ai_model'] = ElevenLabsService::DEFAULT_ELEVENLABS_MODEL;
            if (isset($reqData['language']) && $reqData['language'] == 'en') {
                $reqData['ai_model'] = ElevenLabsService::DEFAULT_ELEVENLABS_MODEL_FOR_ENGLISH;
            }
            $chatbot = ExtVoiceChatbot::create($reqData);

            return JsonResource::make($chatbot);
        }

        return $res->setStatusCode(422);

    }

    // update voice chatbot
    public function update(VoiceChatbotUpdateRequest $request): JsonResource|JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $reqData = $request->validated();

        if (isset($reqData['language']) && $reqData['language'] == 'auto') {
            unset($reqData['language']);
        }

        $chatbot = ExtVoiceChatbot::findOrFail($reqData['id']);
        $chatbot?->update($reqData);

        $this->service->updateAgent($chatbot->id);

        return JsonResource::make($chatbot);
    }

    // delete voice chatbot
    public function delete(Request $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $request->validate(['id' => 'required']);

        $chatbot = $this->service->query()->findOrFail($request->get('id'));

        if ($chatbot->getAttribute('user_id') === Auth::id()) {
            $this->service->deleteAgent($chatbot->agent_id);
            $chatbot->delete();
        } else {
            abort(403);
        }

        return response()->json([
            'message' => 'Voice Chatbot deleted successfully',
            'type'    => 'success',
            'status'  => 200,
        ]);
    }

    /**
     * voice chatbot frame view
     */
    public function frame(string $uuid): View|Response
    {
        $chatbot = ExtVoiceChatbot::whereUuid($uuid)->firstOrFail();
        if ($chatbot) {
            return view('chatbot-voice::frame', compact('chatbot'));
        }

        return response('Incorrect UUID', 404);
    }

    public function checkVoiceBalance(Request $request): ?JsonResponse
    {
        if (Helper::appIsDemo()) {

            $clientIp = Helper::getRequestIp();
            $rateLimiter = new RateLimiter('voice-chat-attempt', 25);

            if ($rateLimiter->attempt($clientIp)) {
                return response()->json(['status' => 'success', 'message' => 'Demo mode'], 200);

            }

            return response()->json(['status' => 'error', 'message' => 'Exceeded messages limit on demo'], 200);
        }

        $uuId = $request->input('uuId');
        $chatbot = ExtVoiceChatbot::whereUuid($uuId)->first();
        if (! empty($chatbot)) {
            $user = $chatbot->user;
            $driver = EntityFacade::driver(EntityEnum::ELEVENLABS_VOICE_CHATBOT)->forUser($user);

            try {
                $driver->redirectIfNoCreditBalance();
            } catch (Exception $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'status'  => 'error',
                ], 200);
            }
        }

        return response()->json(['status' => 'success', 'message' => ''], 200);
    }
}
