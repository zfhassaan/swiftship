<?php

namespace Zfhassaan\SwiftShip\Utility;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Response as FacadeResponse;


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
     */
    public static function LogData($channel,$identifier,$data): null
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
        Log::channel($channel)->info('===== ' . $identifier . '====== ' . json_encode($data));
        return null;
    }
    public static function success($message, $data, $status = Response::HTTP_OK): JsonResponse
    {
        self::LogData('swiftship',' Success ', $data);
        return response()->json([
            'status' => true,
            'code' => Response::HTTP_OK,
            'message' => $message,
            'data' => $data,
        ], Response::HTTP_OK);
    }

    public static function failure($message, $data = [], $code = Response::HTTP_UNPROCESSABLE_ENTITY, $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        self::LogData('swiftship',' Failure ', $data);
        return response()->json([
            'status' => false,
            'code' => $code,
            'message' => $message,
            'data' => $data
        ],$status);
    }

    public static function download(string $binaryContent, string $filename = 'file.pdf'): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $tempPath = storage_path('app/temp/' . $filename);

        // Ensure temp folder exists
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        file_put_contents($tempPath, $binaryContent);

        self::LogData('swiftship', ' Download ', $filename);

        return FacadeResponse::download($tempPath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . basename($filename) . '"',
        ]);
    }
}
