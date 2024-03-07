<?php

class ConstructionStages
{
	private $db;

    /**
     * @var array $validationRules validation rules configuration for each field
     */
    private $validationRules = [
        'name' => 'required|varchar',
        'startDate' => 'required|datetime',
        'endDate' => 'after(startDate)',
        'durationUnit' => 'enum(HOURS|DAYS|WEEKS|default:DAYS)',
        'color' => 'hexcolor',
        'externalId' => 'varchar',
        'status' => 'required|enum(NEW|PLANNED|DELETED|default:NEW)'
    ];

    public function __construct()
	{
		$this->db = Api::getDb();
	}

	public function getAll()
	{
		$stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
		");
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getSingle($id)
	{
		$stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
			WHERE ID = :id
		");
		$stmt->execute(['id' => $id]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function post(ConstructionStagesCreate $data)
	{
        $dataArray = get_object_vars($data);
        Validator::init($dataArray);
        Validator::validateAll($this->validationRules);

        if (Validator::isValidated() === false) {
            return Validator::getErrors();
        }

        $validatedData = Validator::getValidated();


        $stmt = $this->db->prepare("
			INSERT INTO construction_stages
			    (name, start_date, end_date, duration, durationUnit, color, externalId, status)
			    VALUES (:name, :start_date, :end_date, :duration, :durationUnit, :color, :externalId, :status)
			");

		$stmt->execute([
			'name' => $validatedData['name'],
			'start_date' => $validatedData['startDate'],
            'end_date' => $validatedData['endDate'],
			'duration' => $this->setDuration($validatedData['startDate'], $validatedData['endDate'], $validatedData['durationUnit']),
			'durationUnit' => $validatedData['durationUnit'],
			'color' => $validatedData['color'],
			'externalId' => $validatedData['externalId'],
			'status' => $validatedData['status'],

		]);

		return $this->getSingle($this->db->lastInsertId());
	}

    /**
     * Update a record with provided data.
     *
     * Example Data:
     * {
     *  "<field_name_1>": "<field_value_1>",
     *  "<field_name_2>": "<field_value_2>"
     * }
     *
     * @param stdClass $data data to update in JSON format
     * @param int $id Record ID
     * @return array Array with updated record
     */
    public function update(stdClass $data, int $id) :array
    {
        //check if record exist
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM construction_stages WHERE ID = :id");
        $stmt->execute(['id' => $id]);
        $recordFound = ($stmt->fetch(PDO::FETCH_NUM)[0] == 1) ? true: false;
        if (!$recordFound) {
            return ['error_message' => 'Record not found'];
        }

        $dataArray = get_object_vars($data);
        Validator::init($dataArray);
        Validator::validateAll($this->validationRules);

        if (Validator::isValidated() === false) {
            return Validator::getErrors();
        }

        $validatedData = Validator::getValidated();
        $validatedData['id'] = $id;
        $validatedData['duration'] = $this->setDuration($validatedData['startDate'], @$validatedData['endDate'], @$validatedData['durationUnit']);

        // convert field names from camelCase to snake_case
        $convertedKeys = array_map(function ($key) {
            return call_user_func([$this, 'convertFieldName'], $key,  ['durationUnit', 'externalId']);
        }, array_keys($validatedData));
        $validatedData = array_combine($convertedKeys, $validatedData);

        // create placeholders for the values
        $placeHolders = array_map(function ($key) {
            return $key . ' = :' . $key;
        }, array_keys($validatedData));

        $sql = "UPDATE construction_stages SET " . implode(', ', $placeHolders) . " WHERE ID = :id" ;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($validatedData);

        return $this->getSingle($id);
    }

    /**
     * Soft delete a record.
     *
     * Set status field to DELETED
     *
     * @param int $id Record ID
     * @return array Array with updated record
     */
    public function delete(int $id) :array
    {
        // check if record exist
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM construction_stages WHERE ID = :id");
        $stmt->execute(['id' => $id]);
        $recordFound = ($stmt->fetch(PDO::FETCH_NUM)[0] == 1) ? true: false;
        if (!$recordFound) {
            return ['error_message' => 'Record not found'];
        }

        // set deleted status to record
        $stmt = $this->db->prepare("UPDATE construction_stages SET status = 'DELETED' WHERE ID = :id");
        $stmt->execute(['id' => $id]);

        return $this->getSingle($id);
    }

    /**
     * Calculates duration from start date to end date in provided units
     *
     * @param string $startDate Start date in valid PHP datetime format
     * @param string $endDate End date in valid PHP datetime format
     * @param $units Utits to calculation. Can be one of HOURS|DAYS|WEEKS
     * @return float|null Calculated duration or null
     */
    private function setDuration($startDate, $endDate = null, $units = null) :null|float
    {
        if (empty($endDate) || empty($units)) {
            return null;
        }

        $startDate = new DateTime($startDate);
        $endDate = new DateTime($endDate);
        $interval = $startDate->diff($endDate);

        switch ($units) {
            case 'HOURS':
                return $interval->h + ($interval->days * 24);
            case 'DAYS':
                return $interval->days;
            case 'WEEKS':
                return floor($interval->days / 7);
            default:
                return null;
        }
    }

    /**
     * Convert string from camelCase to snake_case
     *
     * @param string $input Input string
     * @param array $skip Values to skip. If $input contain a string which is in $skip array, this string will not be converted
     * @return string Converted field name
     */
    private function convertFieldName(string $input = '', array $skip = []) :string
    {
       if (in_array($input, $skip)) {
           return $input;
       }

       return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}