<?php

namespace zfhassaan\swiftship\Utility;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class Helper
{
    /**
     * Logs the Data to the log channel, its also used to create a new channel if it's not configured in
     * the logging.php file.
     *
     * @param $channel
     * @param $identifier
     * @param $data
     * @return null
     * @noinspection PhpVoidFunctionResultUsedInspection
     */
    public function LogData($channel,$identifier,$data)
    {
        // Check if the specified channel exists in the logging configuration
        if (!config("logging.channels.$channel")) {
            // Create a new channel configuration if it doesn't exist
            config(["logging.channels.$channel" => [
                'driver' => 'daily',
                'path' => storage_path("logs/$channel/$channel.log"),
                'level' => 'debug',
            ]]);
            // Reconfigure the logger with the new channel
            Log::channel($channel);
        }

        return Log::channel($channel)->info('===== ' . $identifier . '====== ' . json_encode($data));
    }
    public function success($data): JsonResponse
    {
        return response()->json([
            'status' => true,
            'code' => ResponseAlias::HTTP_OK,
            'message' => $data
        ]);
    }

    public function failure($data, $code = ResponseAlias::HTTP_INTERNAL_SERVER_ERROR): JsonResponse
    {
        self::LogData('swiftship',' Failure Caused ', $data);
        return response()->json([
            'status' => false,
            'code' => $code,
            'message' => $data
        ]);
    }
}
