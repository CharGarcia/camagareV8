import React from 'react';
import { ActivityIndicator, View } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { useAuth } from '../auth/AuthContext';
import LoginScreen from '../screens/LoginScreen';
import SeleccionEmpresaScreen from '../screens/SeleccionEmpresaScreen';
import MenuScreen from '../screens/MenuScreen';
import SeleccionSerieScreen from '../screens/SeleccionSerieScreen';
import PedidosListScreen from '../screens/PedidosListScreen';
import PedidoFormScreen from '../screens/PedidoFormScreen';

export type RootStackParamList = {
  Login: undefined;
  SeleccionEmpresa: undefined;
  Menu: undefined;
  SeleccionSerie: undefined;
  PedidosList: undefined;
  PedidoForm: { id?: number } | undefined;
};

const Stack = createNativeStackNavigator<RootStackParamList>();

export default function RootNavigator() {
  const { cargando: cargandoAuth, autenticado, requiereSeleccionEmpresa } = useAuth();

  if (cargandoAuth) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" color="#0d6efd" />
      </View>
    );
  }

  return (
    <NavigationContainer>
      <Stack.Navigator screenOptions={{ headerStyle: { backgroundColor: '#0d6efd' }, headerTintColor: '#fff' }}>
        {!autenticado ? (
          <Stack.Screen name="Login" component={LoginScreen} options={{ headerShown: false }} />
        ) : requiereSeleccionEmpresa ? (
          <Stack.Screen name="SeleccionEmpresa" component={SeleccionEmpresaScreen} options={{ headerShown: false }} />
        ) : (
          <>
            <Stack.Screen name="Menu" component={MenuScreen} options={{ headerShown: false }} />
            <Stack.Screen name="SeleccionSerie" component={SeleccionSerieScreen} options={{ headerShown: false }} />
            <Stack.Screen name="PedidosList" component={PedidosListScreen} options={{ title: 'Pedidos' }} />
            <Stack.Screen name="PedidoForm" component={PedidoFormScreen} options={{ title: 'Pedido' }} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}
