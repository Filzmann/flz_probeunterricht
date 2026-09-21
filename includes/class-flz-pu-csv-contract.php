<?php

defined('ABSPATH') || PHP_SAPI === 'cli' || exit;

// Validierungsfehler sind interne/fachliche Meldungen; Escaping erfolgt in der UI.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

final class FlzPuCsvContract
{
	public const VERSION = 'flz_probeunterricht_v1';

	public static function school_header(): array
	{
		return array('format_version', 'record_type', 'school_name', 'capacity');
	}

	public static function participant_header(): array
	{
		return array(
			'format_version',
			'record_type',
			'last_name',
			'first_name',
			'class',
			'email',
			'school_name',
			'lunch',
			'status',
			'created_at',
		);
	}

	/** @param array<int,FlzPuSchool> $schools */
	public static function export_schools(array $schools): array
	{
		return array_map(
			static fn(FlzPuSchool $school): array => array(
				self::VERSION,
				'school',
				(string) $school->name,
				(int) $school->capacity,
			),
			$schools
		);
	}

	/** @param array<int,FlzPuParticipant> $participants */
	public static function export_participants(array $participants): array
	{
		return array_map(
			static fn(FlzPuParticipant $participant): array => array(
				self::VERSION,
				'participant',
				(string) $participant->name,
				(string) $participant->firstName,
				(string) $participant->class,
				(string) $participant->email,
				(string) ($participant->school?->name ?? ''),
				$participant->lunch ? '1' : '0',
				(string) $participant->status,
				(string) $participant->created_at,
			),
			$participants
		);
	}

	/** @return array<int,array{name:string,capacity:int}> */
	public static function parse_schools(array $rows): array
	{
		self::assert_header($rows, self::school_header(), 'Grundschul');
		array_shift($rows);
		$result = array();
		$names = array();
		foreach ($rows as $index => $raw_row) {
			$line = $index + 2;
			$row = self::row($raw_row, count(self::school_header()), $line);
			if (self::VERSION !== $row[0] || 'school' !== $row[1]) {
				throw new UnexpectedValueException('Grundschul-CSV Zeile ' . $line . ': Version oder Datensatztyp ist ungültig.');
			}
			$name = sanitize_text_field($row[2]);
			if ('' === $name || strlen($name) > 255 || isset($names[strtolower($name)])) {
				throw new UnexpectedValueException('Grundschul-CSV Zeile ' . $line . ': Der Schulname fehlt oder kommt doppelt vor.');
			}
			$capacity = self::integer($row[3], $line, 'Kapazität');
			$names[strtolower($name)] = true;
			$result[] = array('name' => $name, 'capacity' => $capacity);
		}
		if (empty($result)) {
			throw new UnexpectedValueException('Die Grundschul-CSV enthält keine Datensätze.');
		}

		return $result;
	}

	/** @return array<int,array<string,mixed>> */
	public static function parse_participants(array $rows): array
	{
		self::assert_header($rows, self::participant_header(), 'Teilnehmenden');
		array_shift($rows);
		$result = array();
		$keys = array();
		foreach ($rows as $index => $raw_row) {
			$line = $index + 2;
			$row = self::row($raw_row, count(self::participant_header()), $line);
			if (self::VERSION !== $row[0] || 'participant' !== $row[1]) {
				throw new UnexpectedValueException('Teilnehmenden-CSV Zeile ' . $line . ': Version oder Datensatztyp ist ungültig.');
			}
			$name = sanitize_text_field($row[2]);
			$first_name = sanitize_text_field($row[3]);
			$class = sanitize_text_field($row[4]);
			$email = strtolower(sanitize_email($row[5]));
			$school_name = sanitize_text_field($row[6]);
			if ('' === $name || '' === $first_name || '' === $class || '' === $school_name) {
				throw new UnexpectedValueException('Teilnehmenden-CSV Zeile ' . $line . ': Ein Pflichtfeld fehlt.');
			}
			if (strlen($name) > 255 || strlen($first_name) > 255 || strlen($class) > 32 || strlen($school_name) > 255) {
				throw new UnexpectedValueException('Teilnehmenden-CSV Zeile ' . $line . ': Ein Textfeld überschreitet die erlaubte Länge.');
			}
			if (!preg_match('/^[\p{L}\p{N} ._-]+$/u', $class)) {
				throw new UnexpectedValueException('Teilnehmenden-CSV Zeile ' . $line . ': Die Klassenangabe enthält ungültige Zeichen.');
			}
			if ('' === $email || !is_email($email)) {
				throw new UnexpectedValueException('Teilnehmenden-CSV Zeile ' . $line . ': Die Eltern-E-Mail ist ungültig.');
			}
			if (!in_array($row[7], array('0', '1'), true)) {
				throw new UnexpectedValueException('Teilnehmenden-CSV Zeile ' . $line . ': Mittagessen muss 0 oder 1 sein.');
			}
			$status = sanitize_text_field($row[8]);
			if (!in_array($status, array('pending', 'active'), true)) {
				throw new UnexpectedValueException('Teilnehmenden-CSV Zeile ' . $line . ': Der Status ist ungültig.');
			}
			$created_at = self::datetime($row[9], $line);
			$key = strtolower(implode('|', array($name, $first_name, $class, $email, $school_name, $created_at)));
			if (isset($keys[$key])) {
				throw new UnexpectedValueException('Teilnehmenden-CSV Zeile ' . $line . ': Der Datensatz kommt doppelt vor.');
			}
			$keys[$key] = true;
			$result[] = array(
				'name' => $name,
				'firstName' => $first_name,
				'class' => $class,
				'email' => $email,
				'school_name' => $school_name,
				'lunch' => '1' === $row[7],
				'status' => $status,
				'created_at' => $created_at,
			);
		}

		return $result;
	}

	private static function assert_header(array $rows, array $expected, string $label): void
	{
		if (empty($rows) || array_map(array(self::class, 'decode'), (array) $rows[0]) !== $expected) {
			throw new UnexpectedValueException('Die ' . $label . '-CSV-Kopfzeile entspricht nicht dem versionierten Format.');
		}
	}

	private static function row($raw_row, int $columns, int $line): array
	{
		if (!is_array($raw_row) || count($raw_row) !== $columns) {
			throw new UnexpectedValueException('CSV-Zeile ' . $line . ': Die Spaltenzahl ist ungültig.');
		}

		return array_map(array(self::class, 'decode'), $raw_row);
	}

	private static function decode($value): string
	{
		$value = trim((string) ($value ?? ''));
		$value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
		return preg_match('/^\'[=+\-@]/', $value) ? substr($value, 1) : $value;
	}

	private static function integer(string $value, int $line, string $label): int
	{
		if (!ctype_digit($value) || (int) $value > 10000) {
			throw new UnexpectedValueException('CSV-Zeile ' . $line . ': ' . $label . ' muss zwischen 0 und 10000 liegen.');
		}

		return (int) $value;
	}

	private static function datetime(string $value, int $line): string
	{
		$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
		$errors = DateTimeImmutable::getLastErrors();
		if (!$date || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
			throw new UnexpectedValueException('Teilnehmenden-CSV Zeile ' . $line . ': Das Anmeldedatum ist ungültig.');
		}

		return $date->format('Y-m-d H:i:s');
	}
}
