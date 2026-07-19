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
import EntregasListScreen from '../screens/EntregasListScreen';
import EntregaScreen from '../screens/EntregaScreen';
import ClientesListScreen from '../screens/ClientesListScreen';
import ClienteFormScreen from '../screens/ClienteFormScreen';
import ProductosListScreen from '../screens/ProductosListScreen';
import ProductoFormScreen from '../screens/ProductoFormScreen';
import FacturasVentaListScreen from '../screens/FacturasVentaListScreen';
import FacturaVentaFormScreen from '../screens/FacturaVentaFormScreen';

export type RootStackParamList = {
  Login: undefined;
  SeleccionEmpresa: undefined;
  Menu: undefined;
  SeleccionSerie: undefined;
  PedidosList: undefined;
  PedidoForm: { id?: number } | undefined;
  EntregasList: undefined;
  Entrega: { id: number };
  ClientesList: undefined;
  ClienteForm: { id?: number } | undefined;
  ProductosList: undefined;
  ProductoForm: { id?: number } | undefined;
  FacturasVentaList: undefined;
  FacturaVentaForm: { id?: number } | undefined;
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
            <Stack.Screen name="EntregasList" component={EntregasListScreen} options={{ title: 'Entregas pendientes' }} />
            <Stack.Screen name="Entrega" component={EntregaScreen} options={{ title: 'Registrar entrega' }} />
            <Stack.Screen name="ClientesList" component={ClientesListScreen} options={{ title: 'Clientes' }} />
            <Stack.Screen name="ClienteForm" component={ClienteFormScreen} options={{ title: 'Cliente' }} />
            <Stack.Screen name="ProductosList" component={ProductosListScreen} options={{ title: 'Productos' }} />
            <Stack.Screen name="ProductoForm" component={ProductoFormScreen} options={{ title: 'Producto' }} />
            <Stack.Screen name="FacturasVentaList" component={FacturasVentaListScreen} options={{ title: 'Facturas de venta' }} />
            <Stack.Screen name="FacturaVentaForm" component={FacturaVentaFormScreen} options={{ title: 'Factura' }} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}
