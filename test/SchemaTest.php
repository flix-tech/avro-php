<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once('test_helper.php');

/**
 * Class SchemaExample
 */
class SchemaExample
{
  var $schema_string;
  var $is_valid;
  var $name;
  var $comment;
  var $normalized_schema_string;

  /**
   * SchemaExample constructor.
   * @param $schema_string
   * @param $is_valid
   * @param null $normalized_schema_string
   * @param null $name
   * @param null $comment
   */
  function __construct($schema_string, $is_valid, $normalized_schema_string=null,
                       $name=null, $comment=null)
  {
    $this->schema_string = $schema_string;
    $this->is_valid = $is_valid;
    $this->name = $name ? $name : $schema_string;
    $this->normalized_schema_string = $normalized_schema_string
      ? $normalized_schema_string : json_encode(json_decode($schema_string, true));
    $this->comment = $comment;
  }
}

/**
 * Class SchemaTest
 */
class SchemaTest extends \PHPUnit\Framework\TestCase
{
  static $examples = array();
  static $valid_examples = array();

  /**
   * @return array
   */
  protected static function make_primitive_examples()
  {
    $examples = array();
    foreach (array('null', 'boolean',
                   'int', 'long',
                   'float', 'double',
                   'bytes', 'string')
             as $type)
    {
      $examples []= new SchemaExample(sprintf('"%s"', $type), true, sprintf('"%s"', $type));
      $examples []= new SchemaExample(sprintf('{"type": "%s"}', $type), true, sprintf('{"type":"%s"}', $type));
    }
    return $examples;
  }

  protected static function make_examples()
  {
    $primitive_examples = array_merge(array(new SchemaExample('"True"', false),
                                            new SchemaExample('{"no_type": "test"}', false),
                                            new SchemaExample('{"type": "panther"}', false)),
                                        self::make_primitive_examples());

    $array_examples = array(
      new SchemaExample('{"type": "array", "items": "long"}', true, '{"type":"array","items":"long"}'),
      new SchemaExample('
    {"type": "array",
     "items": {"type": "enum", "name": "Test", "symbols": ["A", "B"]}}
    ', true, '{"type":"array","items":{"type":"enum","name":"Test","symbols":["A","B"]}}'));

    $map_examples = array(
      new SchemaExample('{"type": "map", "values": "long"}', true, '{"type":"map","values":"long"}'),
      new SchemaExample('
    {"type": "map",
     "values": {"type": "enum", "name": "Test", "symbols": ["A", "B"]}}
    ', true, ));

    $union_examples = array(
      new SchemaExample('["string", "null", "long"]', true, '["string","null","long"]'),
      new SchemaExample('["null", "null"]', false),
      new SchemaExample('["long", "long"]', false),
      new SchemaExample('
    [{"type": "array", "items": "long"}
     {"type": "array", "items": "string"}]
    ', false),
      new SchemaExample('["long",
                          {"type": "long"},
                          "int"]', false),
      new SchemaExample('["long",
                          {"type": "array", "items": "long"},
                          {"type": "map", "values": "long"},
                          "int"]', true, '["long",{"type":"array","items":"long"},{"type":"map","values":"long"},"int"]'),
      new SchemaExample('["long",
                          ["string", "null"],
                          "int"]', false),
      new SchemaExample('["long",
                          ["string", "null"],
                          "int"]', false),
      new SchemaExample('["null", "boolean", "int", "long", "float", "double",
                          "string", "bytes",
                          {"type": "array", "items":"int"},
                          {"type": "map", "values":"int"},
                          {"name": "bar", "type":"record",
                           "fields":[{"name":"label", "type":"string"}]},
                          {"name": "foo", "type":"fixed",
                           "size":16},
                          {"name": "baz", "type":"enum", "symbols":["A", "B", "C"]}
                         ]', true, '["null","boolean","int","long","float","double","string","bytes",{"type":"array","items":"int"},{"type":"map","values":"int"},{"type":"record","name":"bar","fields":[{"name":"label","type":"string"}]},{"type":"fixed","name":"foo","size":16},{"type":"enum","name":"baz","symbols":["A","B","C"]}]'),
      new SchemaExample('
    [{"name":"subtract", "namespace":"com.example",
      "type":"record",
      "fields":[{"name":"minuend", "type":"int"},
                {"name":"subtrahend", "type":"int"}]},
      {"name": "divide", "namespace":"com.example",
      "type":"record",
      "fields":[{"name":"quotient", "type":"int"},
                {"name":"dividend", "type":"int"}]},
      {"type": "array", "items": "string"}]
    ', true, '[{"type":"record","name":"subtract","namespace":"com.example","fields":[{"name":"minuend","type":"int"},{"name":"subtrahend","type":"int"}]},{"type":"record","name":"divide","namespace":"com.example","fields":[{"name":"quotient","type":"int"},{"name":"dividend","type":"int"}]},{"type":"array","items":"string"}]'),
      );

    $fixed_examples = array(
      new SchemaExample('{"type": "fixed", "name": "Test", "size": 1}', true),
      new SchemaExample('
    {"type": "fixed",
     "name": "MyFixed",
     "namespace": "org.apache.hadoop.avro",
     "size": 1}
    ', true),
      new SchemaExample('
    {"type": "fixed",
     "name": "Missing size"}
    ', false),
      new SchemaExample('
    {"type": "fixed",
     "size": 314}
    ', false),
      new SchemaExample('{"type":"fixed","name":"ex","doc":"this should be ignored","size": 314}',
                        true,
                        '{"type":"fixed","name":"ex","size":314}'),
      new SchemaExample('{"name": "bar",
                          "namespace": "com.example",
                          "type": "fixed",
                          "size": 32 }', true,
                        '{"type":"fixed","name":"bar","namespace":"com.example","size":32}'),
      new SchemaExample('{"name": "com.example.bar",
                          "type": "fixed",
                          "size": 32 }', true,
        '{"type":"fixed","name":"bar","namespace":"com.example","size":32}'));

    $fixed_examples []= new SchemaExample(
      '{"type":"fixed","name":"_x.bar","size":4}', true,
      '{"type":"fixed","name":"bar","namespace":"_x","size":4}');
    $fixed_examples []= new SchemaExample(
      '{"type":"fixed","name":"baz._x","size":4}', true,
      '{"type":"fixed","name":"_x","namespace":"baz","size":4}');
    $fixed_examples []= new SchemaExample(
      '{"type":"fixed","name":"baz.3x","size":4}', false);

    $enum_examples = array(
      new SchemaExample('{"type": "enum", "name": "Test", "symbols": ["A", "B"]}', true),
      new SchemaExample('
    {"type": "enum",
     "name": "Status",
     "symbols": "Normal Caution Critical"}
    ', false),
      new SchemaExample('
    {"type": "enum",
     "name": [ 0, 1, 1, 2, 3, 5, 8 ],
     "symbols": ["Golden", "Mean"]}
    ', false),
      new SchemaExample('
    {"type": "enum",
     "symbols" : ["I", "will", "fail", "no", "name"]}
    ', false),
      new SchemaExample('
    {"type": "enum",
     "name": "Test"
     "symbols" : ["AA", "AA"]}
    ', false),
      new SchemaExample('{"type":"enum","name":"Test","symbols":["AA", 16]}',
                        false),
      new SchemaExample('
    {"type": "enum",
     "name": "blood_types",
     "doc": "AB is freaky.",
     "symbols" : ["A", "AB", "B", "O"]}
    ', true),
      new SchemaExample('
    {"type": "enum",
     "name": "blood-types",
     "doc": 16,
     "symbols" : ["A", "AB", "B", "O"]}
    ', false)
      );


    $record_examples = array();
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Test",
     "fields": [{"name": "f",
                 "type": "long"}]}
    ', true, '{"type":"record","name":"Test","fields":[{"name":"f","type":"long"}]}');
    $record_examples []= new SchemaExample('
    {"type": "error",
     "name": "Test",
     "fields": [{"name": "f",
                 "type": "long"}]}
    ', true, '{"type":"error","name":"Test","fields":[{"name":"f","type":"long"}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Node",
     "fields": [{"name": "label", "type": "string"},
                {"name": "children",
                 "type": {"type": "array", "items": "Node"}}]}
    ', true, '{"type":"record","name":"Node","fields":[{"name":"label","type":"string"},{"name":"children","type":{"type":"array","items":"Node"}}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "ListLink",
     "fields": [{"name": "car", "type": "int"},
                {"name": "cdr", "type": "ListLink"}]}
    ', true, '{"type":"record","name":"ListLink","fields":[{"name":"car","type":"int"},{"name":"cdr","type":"ListLink"}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Lisp",
     "fields": [{"name": "value",
                 "type": ["null", "string"]}]}
    ', true, '{"type":"record","name":"Lisp","fields":[{"name":"value","type":["null","string"]}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Lisp",
     "fields": [{"name": "value",
                 "type": ["null", "string",
                          {"type": "record",
                           "name": "Cons",
                           "fields": [{"name": "car", "type": "string"},
                                      {"name": "cdr", "type": "string"}]}]}]}
    ', true, '{"type":"record","name":"Lisp","fields":[{"name":"value","type":["null","string",{"type":"record","name":"Cons","fields":[{"name":"car","type":"string"},{"name":"cdr","type":"string"}]}]}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Lisp",
     "fields": [{"name": "value",
                 "type": ["null", "string",
                          {"type": "record",
                           "name": "Cons",
                           "fields": [{"name": "car", "type": "Lisp"},
                                      {"name": "cdr", "type": "Lisp"}]}]}]}
    ', true, '{"type":"record","name":"Lisp","fields":[{"name":"value","type":["null","string",{"type":"record","name":"Cons","fields":[{"name":"car","type":"Lisp"},{"name":"cdr","type":"Lisp"}]}]}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "HandshakeRequest",
     "namespace": "org.apache.avro.ipc",
     "fields": [{"name": "clientHash",
                 "type": {"type": "fixed", "name": "MD5", "size": 16}},
                {"name": "meta",
                 "type": ["null", {"type": "map", "values": "bytes"}]}]}
    ', true, '{"type":"record","name":"HandshakeRequest","namespace":"org.apache.avro.ipc","fields":[{"name":"clientHash","type":{"type":"fixed","name":"MD5","size":16}},{"name":"meta","type":["null",{"type":"map","values":"bytes"}]}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "HandshakeRequest",
     "namespace": "org.apache.avro.ipc",
     "fields": [{"name": "clientHash",
                 "type": {"type": "fixed", "name": "MD5", "size": 16}},
                {"name": "clientProtocol", "type": ["null", "string"]},
                {"name": "serverHash", "type": "MD5"},
                {"name": "meta",
                 "type": ["null", {"type": "map", "values": "bytes"}]}]}
    ', true, '{"type":"record","name":"HandshakeRequest","namespace":"org.apache.avro.ipc","fields":[{"name":"clientHash","type":{"type":"fixed","name":"MD5","size":16}},{"name":"clientProtocol","type":["null","string"]},{"name":"serverHash","type":"MD5"},{"name":"meta","type":["null",{"type":"map","values":"bytes"}]}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "HandshakeResponse",
     "namespace": "org.apache.avro.ipc",
     "fields": [{"name": "match",
                 "type": {"type": "enum",
                          "name": "HandshakeMatch",
                          "symbols": ["BOTH", "CLIENT", "NONE"]}},
                {"name": "serverProtocol", "type": ["null", "string"]},
                {"name": "serverHash",
                 "type": ["null",
                          {"name": "MD5", "size": 16, "type": "fixed"}]},
                {"name": "meta",
                 "type": ["null", {"type": "map", "values": "bytes"}]}]}
    ', true,
        '{"type":"record","name":"HandshakeResponse","namespace":"org.apache.avro.ipc","fields":[{"name":"match","type":{"type":"enum","name":"HandshakeMatch","symbols":["BOTH","CLIENT","NONE"]}},{"name":"serverProtocol","type":["null","string"]},{"name":"serverHash","type":["null",{"type":"fixed","name":"MD5","size":16}]},{"name":"meta","type":["null",{"type":"map","values":"bytes"}]}]}'
      );
    $record_examples []= new SchemaExample('{"type": "record",
 "namespace": "org.apache.avro",
 "name": "Interop",
 "fields": [{"type": {"fields": [{"type": {"items": "org.apache.avro.Node",
                                           "type": "array"},
                                  "name": "children"}],
                      "type": "record",
                      "name": "Node"},
             "name": "recordField"}]}
', true, '{"type":"record","name":"Interop","namespace":"org.apache.avro","fields":[{"name":"recordField","type":{"type":"record","name":"Node","fields":[{"name":"children","type":{"type":"array","items":"Node"}}]}}]}');
    $record_examples [] = new SchemaExample('{"type": "record",
 "namespace": "org.apache.avro",
 "name": "Interop",
 "fields": [{"type": {"symbols": ["A", "B", "C"], "type": "enum", "name": "Kind"},
             "name": "enumField"},
            {"type": {"fields": [{"type": "string", "name": "label"},
                                 {"type": {"items": "org.apache.avro.Node", "type": "array"},
                                  "name": "children"}],
                      "type": "record",
                      "name": "Node"},
             "name": "recordField"}]}', true, '{"type":"record","name":"Interop","namespace":"org.apache.avro","fields":[{"name":"enumField","type":{"type":"enum","name":"Kind","symbols":["A","B","C"]}},{"name":"recordField","type":{"type":"record","name":"Node","fields":[{"name":"label","type":"string"},{"name":"children","type":{"type":"array","items":"Node"}}]}}]}');

    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Interop",
     "namespace": "org.apache.avro",
     "fields": [{"name": "intField", "type": "int"},
                {"name": "longField", "type": "long"},
                {"name": "stringField", "type": "string"},
                {"name": "boolField", "type": "boolean"},
                {"name": "floatField", "type": "float"},
                {"name": "doubleField", "type": "double"},
                {"name": "bytesField", "type": "bytes"},
                {"name": "nullField", "type": "null"},
                {"name": "arrayField",
                 "type": {"type": "array", "items": "double"}},
                {"name": "mapField",
                 "type": {"type": "map",
                          "values": {"name": "Foo",
                                     "type": "record",
                                     "fields": [{"name": "label",
                                                 "type": "string"}]}}},
                {"name": "unionField",
                 "type": ["boolean",
                          "double",
                          {"type": "array", "items": "bytes"}]},
                {"name": "enumField",
                 "type": {"type": "enum",
                          "name": "Kind",
                          "symbols": ["A", "B", "C"]}},
                {"name": "fixedField",
                 "type": {"type": "fixed", "name": "MD5", "size": 16}},
                {"name": "recordField",
                 "type": {"type": "record",
                          "name": "Node",
                          "fields": [{"name": "label", "type": "string"},
                                     {"name": "children",
                                      "type": {"type": "array",
                                               "items": "Node"}}]}}]}
    ', true,
        '{"type":"record","name":"Interop","namespace":"org.apache.avro","fields":[{"name":"intField","type":"int"},{"name":"longField","type":"long"},{"name":"stringField","type":"string"},{"name":"boolField","type":"boolean"},{"name":"floatField","type":"float"},{"name":"doubleField","type":"double"},{"name":"bytesField","type":"bytes"},{"name":"nullField","type":"null"},{"name":"arrayField","type":{"type":"array","items":"double"}},{"name":"mapField","type":{"type":"map","values":{"type":"record","name":"Foo","fields":[{"name":"label","type":"string"}]}}},{"name":"unionField","type":["boolean","double",{"type":"array","items":"bytes"}]},{"name":"enumField","type":{"type":"enum","name":"Kind","symbols":["A","B","C"]}},{"name":"fixedField","type":{"type":"fixed","name":"MD5","size":16}},{"name":"recordField","type":{"type":"record","name":"Node","fields":[{"name":"label","type":"string"},{"name":"children","type":{"type":"array","items":"Node"}}]}}]}');
    $record_examples []= new SchemaExample('{"type": "record", "namespace": "org.apache.avro", "name": "Interop", "fields": [{"type": "int", "name": "intField"}, {"type": "long", "name": "longField"}, {"type": "string", "name": "stringField"}, {"type": "boolean", "name": "boolField"}, {"type": "float", "name": "floatField"}, {"type": "double", "name": "doubleField"}, {"type": "bytes", "name": "bytesField"}, {"type": "null", "name": "nullField"}, {"type": {"items": "double", "type": "array"}, "name": "arrayField"}, {"type": {"type": "map", "values": {"fields": [{"type": "string", "name": "label"}], "type": "record", "name": "Foo"}}, "name": "mapField"}, {"type": ["boolean", "double", {"items": "bytes", "type": "array"}], "name": "unionField"}, {"type": {"symbols": ["A", "B", "C"], "type": "enum", "name": "Kind"}, "name": "enumField"}, {"type": {"type": "fixed", "name": "MD5", "size": 16}, "name": "fixedField"}, {"type": {"fields": [{"type": "string", "name": "label"}, {"type": {"items": "org.apache.avro.Node", "type": "array"}, "name": "children"}], "type": "record", "name": "Node"}, "name": "recordField"}]}
', true, '{"type":"record","name":"Interop","namespace":"org.apache.avro","fields":[{"name":"intField","type":"int"},{"name":"longField","type":"long"},{"name":"stringField","type":"string"},{"name":"boolField","type":"boolean"},{"name":"floatField","type":"float"},{"name":"doubleField","type":"double"},{"name":"bytesField","type":"bytes"},{"name":"nullField","type":"null"},{"name":"arrayField","type":{"type":"array","items":"double"}},{"name":"mapField","type":{"type":"map","values":{"type":"record","name":"Foo","fields":[{"name":"label","type":"string"}]}}},{"name":"unionField","type":["boolean","double",{"type":"array","items":"bytes"}]},{"name":"enumField","type":{"type":"enum","name":"Kind","symbols":["A","B","C"]}},{"name":"fixedField","type":{"type":"fixed","name":"MD5","size":16}},{"name":"recordField","type":{"type":"record","name":"Node","fields":[{"name":"label","type":"string"},{"name":"children","type":{"type":"array","items":"Node"}}]}}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "ipAddr",
     "fields": [{"name": "addr",
                 "type": [{"name": "IPv6", "type": "fixed", "size": 16},
                          {"name": "IPv4", "type": "fixed", "size": 4}]}]}
    ', true,
    '{"type":"record","name":"ipAddr","fields":[{"name":"addr","type":[{"type":"fixed","name":"IPv6","size":16},{"type":"fixed","name":"IPv4","size":4}]}]}');
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Address",
     "fields": [{"type": "string"},
                {"type": "string", "name": "City"}]}
    ', false);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "name": "Event",
     "fields": [{"name": "Sponsor"},
                {"name": "City", "type": "string"}]}
    ', false);
    $record_examples []= new SchemaExample('
    {"type": "record",
     "fields": "His vision, from the constantly passing bars,"
     "name", "Rainer"}
    ', false);
     $record_examples []= new SchemaExample('
    {"name": ["Tom", "Jerry"],
     "type": "record",
     "fields": [{"name": "name", "type": "string"}]}
    ', false);
     $record_examples []= new SchemaExample('
    {"type":"record","name":"foo","doc":"doc string",
     "fields":[{"name":"bar", "type":"int", "order":"ascending", "default":1}]}
',
                                            true,
         '{"type":"record","name":"foo","doc":"doc string","fields":[{"name":"bar","type":"int","default":1,"order":"ascending"}]}');
     $record_examples []= new SchemaExample('
    {"type":"record", "name":"foo", "doc":"doc string",
     "fields":[{"name":"bar", "type":"int", "order":"bad"}]}
', false);
     // `"default":null` should not be lost in `to_avro`.
     $record_examples []= new SchemaExample(
        '{"type":"record","name":"foo","fields":[{"name":"bar","type":["null","string"],"default":null}]}',
        true,
         '{"type":"record","name":"foo","fields":[{"name":"bar","type":["null","string"],"default":null}]}');
    // Don't lose the "doc" attributes of record fields.
    $record_examples []= new SchemaExample(
      '{"type":"record","name":"foo","fields":[{"name":"bar","type":["null","string"],"doc":"Bar name."}]}',
      true,
      '{"type":"record","name":"foo","fields":[{"name":"bar","type":["null","string"],"doc":"Bar name."}]}');

    $primitive_examples []= new SchemaExample(
        '{ "type": "bytes", "logicalType": "decimal", "precision": 4, "scale": 2 }',
        true
    );
    $fixed_examples []= new SchemaExample(
        '{ "type": "fixed", "size": 32, "name": "hash", "logicalType": "md5" }',
        true,
        '{"type":"fixed","name":"hash","logicalType":"md5","size":32}'
    );
    $enum_examples []= new SchemaExample(
    '{"type": "enum", "logicalType": "foo", "name": "foo", "symbols": ["FOO", "BAR"], "foo": "bar"}',
        true,
        '{"type":"enum","name":"foo","logicalType":"foo","foo":"bar","symbols":["FOO","BAR"]}'
    );
    $array_examples []= new SchemaExample(
    '{"type": "array", "logicalType": "foo", "items": "string", "foo": "bar"}',
        true,
        '{"type":"array","items":"string","logicalType":"foo","foo":"bar"}'
    );
    $map_examples []= new SchemaExample(
        '{"type": "map", "logicalType": "foo", "values": "long", "foo": "bar"}',
        true,
        '{"type":"map","values":"long","logicalType":"foo","foo":"bar"}'
    );
    $record_examples []= new SchemaExample(
        '{ "type": "record", "name": "foo", "logicalType": "bar", "fields": [], "foo": "bar" }',
        true,
        '{"type":"record","name":"foo","logicalType":"bar","foo":"bar","fields":[]}'
    );

    self::$examples = array_merge($primitive_examples,
                                  $fixed_examples,
                                  $enum_examples,
                                  $array_examples,
                                  $map_examples,
                                  $union_examples,
                                  $record_examples);
    self::$valid_examples = array();
    foreach (self::$examples as $example)
      if ($example->is_valid)
        self::$valid_examples []= $example;
    return self::$examples;
  }

  protected function setUp(): void
  {
    if (0 == count(self::$examples))
      self::make_examples();
  }

  function example_provider()
  {
    if (0 == count(self::$examples))
      self::make_examples();
    $ary = array();
    foreach (self::$examples as $example)
      $ary []= array($example);
    return $ary;
  }

  /**
   * @dataProvider example_provider
   */
  function test_parse($example)
  {
    $schema_string = $example->schema_string;
    try {
      $normalized_schema_string = $example->normalized_schema_string;
      $schema = AvroSchema::parse($schema_string);
      $this->assertTrue($example->is_valid,
                        sprintf("schema_string: %s\n",
                                $schema_string));
      $this->assertEquals($normalized_schema_string, strval($schema));
    }
    catch (AvroSchemaParseException $e)
    {
      $this->assertFalse($example->is_valid,
                         sprintf("schema_string: %s\n%s",
                                 $schema_string,
                                 $e->getMessage()));
    }
  }

  function test_enum_default_value()
  {
    $schema_string = '{"type":"enum","name":"blood_types","symbols":["A","B","AB","O"],"default":"A"}';
    $schema = AvroSchema::parse($schema_string);
    $this->assertEquals($schema->default_value(), "A");
    $this->assertTrue($schema->has_default_value());
  }

  function test_int_logical_type()
  {
    $schema_string = '{"type":"int","logicalType":"date"}';
    $schema = AvroSchema::parse($schema_string);
    $this->assertEquals($schema->logical_type(), "date");
  }

  function test_long_logical_type()
  {
    $schema_string = '{"type":"long","logicalType":"timestamp-millis"}';
    $schema = AvroSchema::parse($schema_string);
    $this->assertEquals($schema->logical_type(), "timestamp-millis");
  }

  function test_logical_type()
  {
    $json = '{ "type": "bytes", "logicalType": "decimal", "precision": 4, "scale": 2 }';
    $schema = AvroSchema::parse($json);
    $this->assertEquals($schema->logical_type(), "decimal");
    $this->assertEquals($schema->extra_attributes(), ["precision" => 4, "scale" => 2]);
  }

  /**
   * Test that empty map default value {} is preserved as object, not converted to array []
   */
  function test_empty_map_default_preserved_as_object()
  {
    $json = '{"type": "record", "name": "Test", "fields": [{"name": "properties", "type": {"type": "map", "values": "string"}, "default": {}}]}';
    $schema = AvroSchema::parse($json);

    $output = strval($schema);

    $this->assertStringContainsString('"default":{}', $output,
      'Empty map default should be serialized as {} (object), not [] (array)');
    $this->assertStringNotContainsString('"default":[]', $output,
      'Empty map default should NOT be serialized as [] (array)');
  }

  /**
   * Test non-empty map default preserves key-value pairs
   */
  function test_non_empty_map_default_preserved()
  {
    $json = '{"type": "record", "name": "Test", "fields": [{"name": "properties", "type": {"type": "map", "values": "string"}, "default": {"key": "value"}}]}';
    $schema = AvroSchema::parse($json);

    $output = strval($schema);

    $this->assertStringContainsString('"default":{"key":"value"}', $output);
  }

  /**
   * Test empty record default is preserved as {}
   */
  function test_empty_record_default_preserved_as_object()
  {
    $json = '{"type": "record", "name": "Outer", "fields": [{"name": "inner", "type": {"type": "record", "name": "Inner", "fields": [{"name": "x", "type": "int", "default": 0}]}, "default": {}}]}';
    $schema = AvroSchema::parse($json);

    $output = strval($schema);

    $this->assertMatchesRegularExpression('/"name":"inner".*"default":\{\}/', $output,
      'Empty record default should be serialized as {} (object)');
  }

  /**
   * Test nested map inside record default
   */
  function test_nested_map_default_preserved()
  {
    $json = '{"type": "record", "name": "Test", "fields": [{"name": "data", "type": {"type": "record", "name": "Data", "fields": [{"name": "props", "type": {"type": "map", "values": "string"}}]}, "default": {"props": {}}}]}';
    $schema = AvroSchema::parse($json);

    $output = strval($schema);

    $this->assertStringContainsString('"props":{}', $output,
      'Nested empty map should be serialized as {} (object)');
  }

  /**
   * Test that primitive type in map values is not expanded to object form.
   * "values": "string" should NOT become "values": {"type": "string"}
   */
  function test_primitive_type_not_expanded_to_object()
  {
    $json = '{"type": "record", "name": "Test", "fields": [{"name": "props", "type": {"type": "map", "values": "string"}, "default": {}}]}';
    $schema = AvroSchema::parse($json);

    $output = strval($schema);

    $this->assertStringContainsString('"values":"string"', $output,
      'Primitive type should remain as "string", not {"type":"string"}');
    $this->assertStringNotContainsString('"values":{"type":"string"}', $output,
      'Primitive type should NOT be expanded to object form');
  }
}
