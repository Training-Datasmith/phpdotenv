# Architecture: phpdotenv

## Purpose

Loads environment variables from `.env` files into PHP's environment, `$_ENV`, and/or
`$_SERVER`. Supports immutable loading (first-write wins), mutable loading (overwrites),
nested variable references (`${VAR}`), validation, and pluggable storage backends.

## Directory Structure

```
src/
  Dotenv.php                  # Main entry point; factory methods for common configurations
  Validator.php               # Fluent API for asserting required variables and allowed values

  Exception/
    Exception_Interface.php   # Marker interface for all phpdotenv exceptions
    Invalid_Encoding_Exception.php  # Thrown when the .env file has invalid encoding
    Invalid_File_Exception.php      # Thrown when the .env file cannot be parsed
    Invalid_Path_Exception.php      # Thrown when the file path does not exist
    Validation_Exception.php        # Thrown when a required variable is missing or invalid

  Loader/
    Loader.php            # Implements Loader_Interface; resolves and writes entries to the repository
    Loader_Interface.php  # Contract: load an array of entries into a repository
    Resolver.php          # Resolves nested variable references (${VAR}) within values

  Parser/
    Entry.php             # Value object: a single parsed key/value pair from the .env file
    Entry_Parser.php      # Parses a single line into a key and optional Value
    Lexer.php             # Tokenizes the raw .env file content into individual lines
    Lines.php             # Splits raw content into an array of non-empty lines
    Parser.php            # Orchestrates Lexer + Entry_Parser; returns Entry[]
    Parser_Interface.php  # Contract: parse a string into Entry[]
    Value.php             # Value object: a parsed value with its interpolation parts

  Repository/
    Adapter_Repository.php   # Implements Repository_Interface using adapter chains
    Repository_Builder.php   # Fluent builder for constructing repositories with custom adapters
    Repository_Interface.php # Contract: get/set/clear individual environment variables

    Adapter/
      Adapter_Interface.php   # Combined Reader+Writer adapter interface
      Apache_Adapter.php      # Reads from Apache's getenv (CGI mode)
      Array_Adapter.php       # In-memory array backend (useful for testing/parsing)
      Env_Const_Adapter.php   # Reads/writes $_ENV superglobal
      Guarded_Writer.php      # Decorator: prevents overwriting an existing value
      Immutable_Writer.php    # Decorator: disables all writes (read-only after load)
      Multi_Reader.php        # Reads from multiple readers in order (first non-null wins)
      Multi_Writer.php        # Writes to multiple writers in sequence
      Putenv_Adapter.php      # Reads/writes via putenv()/getenv() (process environment)
      Reader_Interface.php    # Contract: read a single variable by name
      Replacing_Writer.php    # Decorator: allows overwriting existing values
      Server_Const_Adapter.php  # Reads/writes $_SERVER superglobal

  Store/
    File_Store.php    # Reads content from one or more .env files on disk
    Store_Builder.php # Fluent builder for constructing File_Store instances
    Store_Interface.php  # Contract: return the raw .env content as a string
    String_Store.php  # Returns a caller-provided string as the .env content

    File/
      Paths.php   # Resolves the list of candidate file paths
      Reader.php  # Reads and concatenates file content with encoding detection

  Util/
    Regex.php  # Functional wrapper around preg_match() returning Option-style result
    Str.php    # UTF-8-safe string utilities (length, substr)
```

## Key Design Decisions

### Adapter Chain for Repository

The repository is built from composable adapter decorators. `Guarded_Writer` prevents
overwriting (immutable mode), `Replacing_Writer` allows it (mutable mode). `Multi_Reader`
and `Multi_Writer` fan out to all registered adapters. This design lets callers choose
exactly which targets (putenv, $_ENV, $_SERVER, in-memory) to read from and write to.

### Store Separates "Where" from "How"

`Store_Interface` provides raw `.env` content as a string. The `Parser` and `Loader` are
unaware of whether content came from a file or a string literal. This makes `parse()` (the
static in-memory method on `Dotenv`) trivial to implement without duplicating logic.

### Short-Circuit File Loading

`Store_Builder::short_circuit()` causes `File_Store` to stop at the first successfully
read file. This enables fallback chains like `.env.local`, `.env.test`, `.env` without
reading all of them.

## Extension Points

- **Custom `Repository_Interface`** — inject a bespoke repository to store variables in
  a different target (e.g., a Redis-backed store).
- **Custom `Adapter_Interface`** — implement Reader/Writer adapters for custom storage.
- **Custom `Parser_Interface`** — replace the default parser with a different `.env` syntax.
- **Custom `Store_Interface`** — load `.env` content from a remote URL, database, etc.

## Dependency Flow

```
Dotenv::create_mutable()
  └─ Repository_Builder → Adapter_Repository (Multi_Reader + Multi_Writer)
       (default: Env_Const_Adapter + Server_Const_Adapter, optionally + Putenv_Adapter)

Dotenv::load()
  └─ Store_Interface::read() → raw .env string
       └─ Parser_Interface::parse() → Entry[]
            └─ Loader_Interface::load(repository, entries)
                 └─ Resolver::resolve() (expand ${VAR} references)
                      └─ Repository_Interface::set()
```
