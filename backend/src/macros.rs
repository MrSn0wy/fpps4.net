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

pub trait PolarResultUnwrap<T> {
    fn polar_unwrap(self, message: &str, show_error: bool) -> T;
}

pub trait PolarOptionUnwrap<T> {
    fn polar_unwrap(self, message: &str) -> T;
}


impl<T, E: std::fmt::Debug> PolarResultUnwrap<T> for Result<T, E> {
    fn polar_unwrap(self, message: &str, show_error: bool) -> T {
        match self {
            Ok(value) => value,
            Err(err) => {
                if show_error {
                    panic_red!("[err]: {}, {:?}", message, err);

                } else {
                    panic_red!("[err]: {}", message);
                }
            }
        }
    }
}

impl<T> PolarOptionUnwrap<T> for Option<T> {
    fn polar_unwrap(self, message: &str) -> T {
        match self {
            Some(value) => value,
            None => panic_red!("[err]: {}", message),
        }
    }
}