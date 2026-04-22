<?php

namespace quanzo\mcp\classes\log;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use quanzo\mcp\helpers\JsonHelper;

/**
 * Класс FileLogger
 *
 * Файловый логгер, реализующий PSR-3 LoggerInterface.
 * Предназначен для записи логов в файл с поддержкой различных уровней логирования.
 * Автоматически создает директорию для логов при необходимости.
 */
class FileLogger extends AbstractLogger
{
    /**
     * Путь к файлу лога
     * @var string
     */
    private $logFile;

    /**
     * Минимальный уровень логирования
     * @var string
     */
    private $logLevel;

    /**
     * Карта приоритетов уровней логирования
     * @var array
     */
    private const LEVEL_PRIORITY = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7
    ];

    /**
     * Конструктор FileLogger
     *
     * @param string $logFile Полный путь к файлу лога
     * @param string $logLevel Минимальный уровень логирования (по умолчанию INFO)
     *
     * @throws \InvalidArgumentException Если указан недопустимый уровень логирования
     */
    public function __construct(string $logFile, string $logLevel = LogLevel::INFO)
    {
        if (!isset(self::LEVEL_PRIORITY[$logLevel])) {
            throw new \InvalidArgumentException(
                "Недопустимый уровень логирования: $logLevel. Допустимые значения: " .
                implode(', ', array_keys(self::LEVEL_PRIORITY))
            );
        }

        $this->logFile = $logFile;
        $this->logLevel = $logLevel;

        // Создаем директорию для логов если не существует
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Записывает лог-сообщение с указанным уровнем
     *
     * @param mixed $level Уровень логирования
     * @param mixed $message Текст сообщения
     * @param array $context Контекстные данные
     *
     * @return void
     *
     * @throws \Psr\Log\InvalidArgumentException Если уровень логирования недопустим
     */
    public function log($level, $message, array $context = []): void
    {
        // Проверяем корректность уровня логирования
        if (!isset(self::LEVEL_PRIORITY[$level])) {
            throw new \Psr\Log\InvalidArgumentException(
                "Недопустимый уровень логирования: $level"
            );
        }

        // Проверяем уровень логирования
        if (self::LEVEL_PRIORITY[$level] < self::LEVEL_PRIORITY[$this->logLevel]) {
            return;
        }

        $contextString = '';
        if (!empty($context)) {
            $contextString = ' ' . JsonHelper::encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $logMessage = sprintf(
            "[%s] [%s] %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $this->interpolate($message, $context),
            $contextString
        );

        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Интерполирует контекстные значения в сообщение
     *
     * @param string $message Шаблон сообщения с плейсхолдерами {key}
     * @param array $context Ассоциативный массив значений для замены
     *
     * @return string Сообщение с подставленными значениями
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }
}
