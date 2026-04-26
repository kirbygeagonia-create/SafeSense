    public function run() {
        // Sample patient data
        $patients = [
            [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'phone' => '123-456-7890',
                'address' => '123 Main St, City, State 12345',
                'date_of_birth' => '1980-01-15',
                'gender' => 'male'
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane.smith@example.com',
                'phone' => '098-765-4321',
                'address' => '456 Oak Ave, Town, State 67890',
                'date_of_birth' => '1990-05-22',
                'gender' => 'female'
            ],
            [
                'name' => 'Robert Johnson',
                'email' => 'robert.j@example.com',
                'phone' => '555-123-4567',
                'address' => '789 Pine Rd, Village, State 11223',
                'date_of_birth' => '1975-11-30',
                'gender' => 'male'
            ]
        ];
        
        // Insert sample data
        foreach ($patients as $patient) {
            $sql = 'INSERT INTO patients (name, email, phone, address, date_of_birth, gender) 
                    VALUES (:name, :email, :phone, :address, :date_of_birth, :gender)';
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($patient);
        }
        
        echo 'Patients seeded successfully';
    }