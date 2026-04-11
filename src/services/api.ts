// Importamos axios (para hacer peticiones HTTP) 
// e InternalAxiosRequestConfig (para tipar correctamente el interceptor)
import axios, { InternalAxiosRequestConfig } from "axios";

// Importamos AsyncStorage para guardar y leer el token del usuario localmente
import AsyncStorage from "@react-native-async-storage/async-storage";

// URL base de la API (en este caso tu backend de WordPress con JWT activado)
const API_BASE = "https://app.smartcards.com.co/wp-json";

// Creamos una instancia de axios llamada "api"
// Aquí definimos la baseURL, el tiempo máximo de espera (timeout)
// y el tipo de contenido que enviará por defecto (JSON)
export const api = axios.create({
  baseURL: API_BASE,
  timeout: 12000, // 12 segundos antes de que axios cancele la petición
  headers: { "Content-Type": "application/json" },
});

// Interceptor de solicitudes (request interceptor)
// Este fragmento se ejecuta automáticamente ANTES de cada petición que haga axios
api.interceptors.request.use(
  // Función asíncrona que recibe la configuración de la petición
  async (config: InternalAxiosRequestConfig) => {

    // Intentamos obtener el token JWT almacenado en el dispositivo
    const token = await AsyncStorage.getItem("@sc_token");

    // Si existe un token, lo agregamos al header "Authorization"
    // usando el formato estándar: Bearer <token>
    if (token) {
      config.headers = { 
        ...(config.headers as any),  // conservamos cualquier header existente
        Authorization: `Bearer ${token}` // añadimos el token al header
      } as any;
    }

    // Retornamos la configuración final (con o sin token)
    return config;
  },

  // En caso de error en el proceso del interceptor, se rechaza la promesa
  (error) => Promise.reject(error)
);
