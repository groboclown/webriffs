Source
======

The source files are RESTful API used for accessing the different parts of the
data model.

For the most part, the pattern follows:

    <?php

    namespace WebRiffs;

    use Tonic;
    use Base;

    class XResource extends Base\Resource {
        protected function getRequestData() {
            $data = $this->request->data;
            return array(
                'Field_Name' => validateFieldName($data['Field_Name']),
                ...
            );
        }

        protected function validateFieldName($val) {
            if (not valid($val)) {
                addValidationError('Field_Name', $val);
            }
        }
    }

    /**
     * @uri /x
     */
    class XCollection extends XResource {
        /**
         * @method GET
         */
        public function list() {
            $db = getDB();
            $stmt = $db->('SELECT a, b, c FROM x');
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $stmt->execute();
            return new Tonic\Response(200, $stmt->fetchAll());
        }

        /**
         * @method POST
         * @secure X create
         */
        public function create() {}
        FIXME
    }
