<?php

namespace App\Extensions\ChatbotVoice\System\Http\Controllers;

use App\Extensions\ChatbotVoice\System\Enums\TrainTypeEnum;
use App\Extensions\ChatbotVoice\System\Http\Requests\Train\DataRequest;
use App\Extensions\ChatbotVoice\System\Http\Requests\Train\FileRequest;
use App\Extensions\ChatbotVoice\System\Http\Requests\Train\TextRequest;
use App\Extensions\ChatbotVoice\System\Http\Requests\Train\TrainRequest;
use App\Extensions\ChatbotVoice\System\Http\Requests\Train\TrainUrlRequest;
use App\Extensions\ChatbotVoice\System\Http\Resources\ChatbotTrainResource;
use App\Extensions\ChatbotVoice\System\Models\ExtVoicechatbotTrain;
use App\Extensions\ChatbotVoice\System\Parsers\LinkParser;
use App\Extensions\ChatbotVoice\System\Services\ChabotVoiceService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class ChatbotVoiceTrainController extends Controller
{
    public function __construct(public ChabotVoiceService $service) {}

    /**
     * return voice chatbot trian data
     */
    public function trainData(DataRequest $request): AnonymousResourceCollection
    {
        return ChatbotTrainResource::collection(
            $this->service->query()
                ->findOrFail($request->validated('id'))
                ?->trains()
                ->when($request->validated('type'), fn ($query) => $query->where('type', $request->validated('type')))
                ->get()
        );
    }

    /**
     * delete voice chabot trian data
     */
    public function delete(TrainRequest $request): JsonResponse
    {
        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        $trains = $chatbot->trains()->whereIn('id', $request->validated('data'))->get();
        foreach ($trains as $train) {
            if ($train->trained_at && $train->doc_id) {
                $train->trained_at = null;
                $train->save();

                $this->service->updateAgentWithKnowledgebase($train->chatbot_id);
                $this->service->deleteKnowledgebase($train->doc_id);
            }
            $train->delete();
        }

        return response()->json([
            'message' => 'Deleted successfully',
            'status'  => 200,
        ]);
    }

    /**
     * store file train data on database
     */
    public function trainFile(FileRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $defaultDisk = 'public';
        $path = $file->store('chatbot-voice', ['disk' => $defaultDisk]);

        ExtVoicechatbotTrain::create([
            'type'       => TrainTypeEnum::file,
            'name'		     => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'user_id'	   => Auth::id(),
            'chatbot_id' => $chatbot->getKey(),
            'file'       => $path,
        ]);

        return ChatbotTrainResource::collection(
            $chatbot->trains()->whereNotNull('file')->get()
        );
    }

    /**
     * store text train data on database
     */
    public function trainText(TextRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        ExtVoicechatbotTrain::create([
            'type'       => TrainTypeEnum::text,
            'user_id'    => Auth::id(),
            'chatbot_id' => $chatbot->getKey(),
            'name'       => $request->validated('title'),
            'text'       => $request->validated('content'),
        ]);

        return ChatbotTrainResource::collection(
            $chatbot->trains()
                ->wherenull('file')
                ->whereNull('url')->get()
        );
    }

    /**
     * store url train data on database
     */
    public function trainUrl(TrainUrlRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        app(LinkParser::class)
            ->setBaseUrl($request->validated('url'))
            ->crawl((bool) $request->validated('single'))
            ->insertEmbeddings($chatbot);

        return ChatbotTrainResource::collection(
            $chatbot->trains()->whereNotNull('url')->get()
        );
    }

    /**
     * train on elevenlabs
     */
    public function generateEmbedding(TrainRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        ini_set('max_execution_time', -1);

        $data = $request->validated('data');
        $trains = ExtVoicechatbotTrain::query()
            ->whereNull('trained_at')
            ->whereIn('id', $data)
            ->get();

        foreach ($trains as $train) {
            $content = '';
            if ($train->type == 'text') {
                $content = $train->text;
            } elseif ($train->type == 'url') {
                $content = $train->url;
            } elseif ($train->type == 'file') {
                $defaultDisk = 'public';
                $path = $train->file;
                $storagePath = config('filesystems.disks.' . $defaultDisk . '.root') . '/' . $path;

                $content = new UploadedFile(
                    $storagePath,
                    basename($path),
                    mime_content_type($storagePath),
                    null,
                    true
                );
            }

            $res = $this->service->addKnowledgebase($train->type, $content, $train->name);

            if ($res->getData()->status == 'success') {
                $train->update([
                    'name' 			      => $res->getData()->resData?->name,
                    'doc_id' 		     => $res->getData()->resData?->id,
                    'trained_at'    => now(),
                ]);
            }
        }

        $this->service->updateAgentWithKnowledgebase($trains[0]->chatbot_id);

        return ChatbotTrainResource::collection($chatbot->trains()->whereIn('id', $data)->get());
    }
}
