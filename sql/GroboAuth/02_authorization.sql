databaseChangeLog:
    - changeSet:
        id: 1
        author: Groboclown
        changes:
            - createTable:
                tableName: USER_LOGIN
                columns:
                    - column:
                        name: User_Login_Id
                        type: int
                        autoIncrement: true
                        constraints:
                            primaryKey: true
                            primaryKeyName: User_Login_Id_Key
                    - column:
                        name: User_Id
                        type: int
                        constraints:
                            nullable: false
                            foreignKeyName: User_Id_Key
                    - column:
                        name: User_Agent
                        type: nvarchar(64)
                        constraints:
                            nullable: false
                    - column:
                        name: Remote_Address
                        type: nvarchar(2048)
                    - column:
                        name: Forwarded_For
                        type: nvarchar(2048)
                    - column:
                        name: Authorization_Challenge
                        type: nvarchar(2048)
                        constraints:
                            nullable: false
                    - column:
                        name: Expires_On
                        type: datetime
                        constraints:
                            nullable: false
                    - column:
                        name: Created_On
                        type: timestamp
                        constraints:
                            nullable: false
                    - column:
                        name: Last_Updated_On
                        type: timestamp