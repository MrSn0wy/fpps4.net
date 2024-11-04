// purely cosmetic
#[macro_export]
macro_rules! panic_red {
    ($($arg:tt)*) => {{
        eprintln!("\x1b[31;1m{}\x1b[0m", format!($($arg)*));
        std::process::exit(1);
    }};
}

#[macro_export]
macro_rules! eprintln_red {
    ($($arg:tt)*) => {{
        eprintln!("\x1b[31;1m{}\x1b[0m", format!($($arg)*));
    }};
}

#[macro_export]
macro_rules! println_green {
    ($($arg:tt)*) => {{
        println!("\x1b[32m{}\x1b[0m", format!($($arg)*));
    }};
}

#[macro_export]
macro_rules! println_cyan {
    ($($arg:tt)*) => {{
        println!("\x1b[36;1m{}\x1b[0m", format!($($arg)*));
    }};
}